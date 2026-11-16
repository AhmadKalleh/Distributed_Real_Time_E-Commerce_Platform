<?php

namespace App\Console\Commands;

use App\Http\Middleware\StickyReadAfterWrite;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Shared\Contracts\CacheStore;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ReadProof extends Command
{
    protected $signature = 'performance:read-proof {--user=1} {--wait=6}';
    protected $description = 'Proves: normal reads use the replica, a read right after a write uses the primary';

    public function handle(): int
    {
        $user = (int) $this->option('user');
        $conn = DB::connection();
        $primary = (int) $conn->getPdo()->query('select @@server_id')->fetchColumn();
        $label = fn (int $id) => $id === $primary ? "PRIMARY (server_id=$id)" : "REPLICA (server_id=$id)";
        app(CacheStore::class)->forget("sticky_write:$user");

        // Runs $work inside the real middleware, like one HTTP request, then resets the connection.
        $run = function (string $method, Closure $work) use ($user, $conn) {
            $result = null;
            $request = Request::create('/api/v1/products', $method);
            $request->setUserResolver(fn () => (object) ['id' => $user]);

            (new Pipeline(app()))->send($request)->through([StickyReadAfterWrite::class])
                ->then(function () use (&$result, $work) {
                    $result = $work();

                    return new Response('', 200);
                });

            $conn->forgetRecordModificationState();
            $conn->useWriteConnectionWhenReading(false);

            return $result;
        };

        $read = fn (int $id) => [(int) DB::selectOne('select @@server_id as id')->id, Product::whereKey($id)->exists()];
        $lines = [];
        $ok = true;
        $check = function (string $title, array $r, bool $wantPrimary) use (&$lines, &$ok, $label, $primary) {
            [$server, $found] = $r;
            $pass = $found && (($server === $primary) === $wantPrimary);
            $ok = $ok && $pass;
            $lines[] = "$title\n   answered by: {$label($server)} | row found: " . ($found ? 'yes' : 'no') . ' | ' . ($pass ? 'PASS' : 'FAIL') . "\n";
        };

        $existing = Product::orderBy('id')->value('id');
        $category = DB::table('categories')->value('id');

        $check("1) User $user opens product #$existing (normal read)", $run('GET', fn () => $read($existing)), false);

        $new = $run('POST', fn () => Product::create([
            'category_id' => $category,
            'name' => 'Read proof ' . now()->format('H:i:s'),
            'description' => 'Created by performance:read-proof',
            'price' => '10.00',
            'is_active' => true,
            'slug' => 'read-proof-' . Str::lower(Str::random(8)),
        ])->id);
        $lines[] = "2) User $user creates product #$new (write goes to the PRIMARY)\n";

        $check("3) User $user opens #$new right after the write", $run('GET', fn () => $read($new)), true);

        $this->info('Waiting for the 5 s window to expire...');
        sleep((int) $this->option('wait'));
        $check("4) User $user opens #$new again after the window", $run('GET', fn () => $read($new)), false);

        $report = "READ-YOUR-WRITES PROOF - " . now()->format('Y-m-d H:i:s') . "\n"
            . "server_id tells which database answered (PRIMARY = writes, REPLICA = normal reads).\n"
            . "Rule: after a write, the same user reads from the PRIMARY for 5 seconds.\n\n"
            . implode("\n", $lines)
            . "\nRESULT: " . ($ok ? 'PASS' : 'FAIL') . "\n";

        File::ensureDirectoryExists(base_path('docs/performance'));
        File::put(base_path('docs/performance/replica-read-proof.txt'), $report);
        $this->line($report);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
