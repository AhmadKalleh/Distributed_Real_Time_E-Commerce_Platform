FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev && node -e "require.resolve('dotenv')"

COPY --chown=node:node . .

USER node

EXPOSE 50051

CMD ["npm", "start"]