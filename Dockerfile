FROM wordpress:php8.4-apache

# Install tools required for WordPress development, testing and packaging.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    subversion \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Fail the build if PHP is below the required security-fix level.
RUN php -r "if (version_compare(PHP_VERSION, '8.4.27', '<')) { fwrite(STDERR, 'PHP 8.4.27+ required for this development image; current: ' . PHP_VERSION . PHP_EOL); exit(1); }"

# Install WP-CLI.
RUN curl -o /usr/local/bin/wp \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

# Allow Apache to listen on port 8080 internally as well as port 80.
RUN echo "Listen 8080" >> /etc/apache2/ports.conf

COPY docker/apache-8080.conf /etc/apache2/sites-available/wordpress-8080.conf

RUN a2ensite wordpress-8080