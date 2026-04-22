# syntax=docker/dockerfile:1
FROM node:20-slim

# Chromium dependencies required by Puppeteer
RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium \
        fonts-liberation \
        libasound2 \
        libatk-bridge2.0-0 \
        libatk1.0-0 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libdrm2 \
        libexpat1 \
        libgbm1 \
        libglib2.0-0 \
        libgtk-3-0 \
        libnspr4 \
        libnss3 \
        libpango-1.0-0 \
        libpangocairo-1.0-0 \
        libx11-6 \
        libx11-xcb1 \
        libxcb1 \
        libxcomposite1 \
        libxcursor1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libxi6 \
        libxkbcommon0 \
        libxrandr2 \
        libxrender1 \
        libxshmfence1 \
        libxss1 \
        libxtst6 \
        xdg-utils \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Wskaż Puppeteerowi preinstalowany Chromium
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    CHROME_EXECUTABLE=/usr/bin/chromium

WORKDIR /app

# Zależności Node.js
COPY package.json package-lock.json* ./
RUN npm ci --omit=dev

# Kod serwisu
COPY . .

# Katalog danych (SQLite + sesja)
RUN mkdir -p /app/data

EXPOSE 3400

CMD ["node", "server.js"]
