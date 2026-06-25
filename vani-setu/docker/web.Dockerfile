FROM nginx:1.29.5-alpine

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html
