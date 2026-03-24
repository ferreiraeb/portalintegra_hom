# ===========================
#  Portal Integra - Dockerfile
#  PHP 8.2 + Apache (Debian 12)
#  SQL Server (ODBC 18 + sqlsrv)
#  LDAP + ZIP + mod_rewrite
# ===========================
FROM php:8.2-apache

ENV DEBIAN_FRONTEND=noninteractive

# ------------------------------------------------------------
# 1) Utilitários base, toolchain de build e libs necessárias
# ------------------------------------------------------------
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      apt-transport-https \
      ca-certificates \
      curl \
      gnupg \
      lsb-release \
      # Toolchain p/ compilar extensões PECL
      $PHPIZE_DEPS \
      libssl-dev \
      # ODBC base
      unixodbc \
      unixodbc-dev \
      # Kerberos/GSSAPI (dependência comum do msodbcsql)
      libgssapi-krb5-2 \
      # LDAP build
      libldap2-dev \
      libsasl2-dev \
      # ZIP build
      libzip-dev \
      zlib1g-dev; \
    rm -rf /var/lib/apt/lists/*

# ------------------------------------------------------------
# 2) Repositório Microsoft (ODBC 18) + ferramentas
# ------------------------------------------------------------
RUN set -eux; \
    curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /usr/share/keyrings/ms.gpg; \
    echo "deb [arch=amd64 signed-by=/usr/share/keyrings/ms.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list; \
    apt-get update; \
    ACCEPT_EULA=Y apt-get install -y --no-install-recommends \
      msodbcsql17 \
      mssql-tools18; \
    rm -rf /var/lib/apt/lists/*

# Adiciona mssql-tools18 ao PATH
ENV PATH="$PATH:/opt/mssql-tools18/bin"

# ------------------------------------------------------------
# 3) Extensões PHP para SQL Server (PECL) – versão fixada
# ------------------------------------------------------------
RUN set -eux; \
    pecl channel-update pecl.php.net; \
    printf "\n" | pecl install -o -f sqlsrv-5.12.0; \
    printf "\n" | pecl install -o -f pdo_sqlsrv-5.12.0; \
    docker-php-ext-enable sqlsrv pdo_sqlsrv; \
    php -m | grep -Ei "sqlsrv|pdo_sqlsrv"

# ------------------------------------------------------------
# 4) Extensão LDAP (com SASL)
# ------------------------------------------------------------
ENV CFLAGS="$CFLAGS -DLDAP_DEPRECATED=1"
RUN set -eux; \
    docker-php-ext-configure ldap --with-ldap=/usr --with-ldap-sasl; \
    docker-php-ext-install -j"$(nproc)" ldap

# ------------------------------------------------------------
# 5) Extensão ZIP (ZipArchive)
# ------------------------------------------------------------
RUN set -eux; \
    docker-php-ext-configure zip; \
    docker-php-ext-install -j"$(nproc)" zip

# ------------------------------------------------------------
# 6) Apache: mod_rewrite + VirtualHost (+ ServerName global opcional)
# ------------------------------------------------------------
RUN a2enmod rewrite

COPY docker/vhosts.conf /etc/apache2/sites-available/portal.conf
RUN a2dissite 000-default && a2ensite portal

# COPY docker/servername.conf /etc/apache2/conf-available/servername.conf
# RUN a2enconf servername

# ------------------------------------------------------------
# 7) Ajustes de PHP (timezone e limites de upload)
# ------------------------------------------------------------
RUN set -eux; \
    { \
      echo 'date.timezone=America/Sao_Paulo'; \
      echo 'upload_max_filesize=32M'; \
      echo 'post_max_size=32M'; \
      echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/99-portal-integra.ini

# ------------------------------------------------------------
# 8) Aplicação
# ------------------------------------------------------------
WORKDIR /var/www/html
COPY app/ /var/www/html/

# Permissões padrão
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
