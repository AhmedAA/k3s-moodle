FROM public.ecr.aws/aikido-dev/php:8.2.35-apache

# Install Moodle required dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libicu-dev libxml2-dev libsodium-dev \
    mariadb-client unzip rsync curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli zip gd intl soap opcache sodium exif pcntl \
    && a2enmod rewrite

COPY 99-custom.ini /usr/local/etc/php/conf.d/

ENV MOODLE_VERSION=503
RUN curl -L https://download.moodle.org/download.php/direct/stable${MOODLE_VERSION}/moodle-latest-${MOODLE_VERSION}.tgz | tar -xz -C /var/www/ \
    && rm -rf /var/www/html \
    && mv /var/www/moodle /var/www/html

# Download and extract Academi theme
RUN curl -L -o /tmp/academi.zip "https://github.com/lmsace/academi/releases/download/v5.3.0/theme_academi-v5.3.zip" \
    && unzip /tmp/academi.zip -d /var/www/html/theme/ \
    && rm /tmp/academi.zip

# Set permissions for webroot and create moodledata
RUN chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata
