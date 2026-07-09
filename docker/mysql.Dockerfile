FROM mysql:8.0

ENV MYSQL_DATABASE=internship_portal
ENV MYSQL_USER=interntrack
ENV MYSQL_PASSWORD=secret
ENV MYSQL_ROOT_PASSWORD=rootsecret

# Auto-import schema on first boot
COPY ../database/schema.sql /docker-entrypoint-initdb.d/01_schema.sql

EXPOSE 3306
