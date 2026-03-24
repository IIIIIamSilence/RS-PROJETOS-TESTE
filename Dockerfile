FROM php:8.2-apache

# 1. Copia todos os arquivos da raiz do GitHub para a raiz do servidor
COPY . /var/www/html/

# 2. Dá as permissões corretas para o Apache ler tudo
RUN chown -R www-data:www-data /var/www/html

# 3. Informa a porta padrão
EXPOSE 80
