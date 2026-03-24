FROM php:8.2-apache

# Habilita o módulo de variáveis de ambiente do Apache
RUN a2enmod env

# Copia os arquivos
COPY . /var/www/html/

# Esta linha é o segredo: ela força o Apache a passar as variáveis para o PHP
RUN echo "PassEnv GEMINI_API_KEY" >> /etc/apache2/conf-enabled/expose-env.conf

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
