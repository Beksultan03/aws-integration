FROM nginx:alpine

COPY ./docker/production/nginx/default.conf /etc/nginx/conf.d/default.conf

COPY . /var/www/html

EXPOSE 80
EXPOSE 443

CMD ["nginx", "-g", "daemon off;"]
