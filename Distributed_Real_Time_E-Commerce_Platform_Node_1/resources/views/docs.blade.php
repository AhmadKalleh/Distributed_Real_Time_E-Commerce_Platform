<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>E-Commerce API Docs</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>body { margin: 0; background: #fafafa; }</style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script>
    window.ui = SwaggerUIBundle({
        url: '{{ url('/docs/openapi.yaml') }}',
        dom_id: '#swagger-ui',
        deepLinking: true,
        persistAuthorization: true,
        displayRequestDuration: true,
        tryItOutEnabled: true,
    });
</script>
</body>
</html>
