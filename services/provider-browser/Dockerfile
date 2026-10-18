FROM mcr.microsoft.com/playwright:v1.64.0-noble
WORKDIR /opt/provider-browser
RUN apt-get update && apt-get install -y --no-install-recommends tesseract-ocr tesseract-ocr-eng && apt-get clean
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts && npm cache clean --force
COPY *.mjs ./
COPY subscription_agent.js ./
USER pwuser
CMD ["node", "server.mjs"]
