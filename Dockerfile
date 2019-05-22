FROM prestashop/base:7.2-apache
LABEL maintainer="Thomas Nabord <thomas.nabord@prestashop.com>"

ARG PRESTASHOP_VERSION=${PRESTASHOP_VERSION}
ENV PRESTASHOP_VERSION=${PRESTASHOP_VERSION}
# Get PrestaShop
ADD "https://www.prestashop.com/download/old/prestashop_${PRESTASHOP_VERSION}.zip" /tmp/prestashop.zip

# Extract
RUN mkdir -p /tmp/data-ps \
	&& unzip -q /tmp/prestashop.zip -d /tmp/data-ps/ \
	&& bash /tmp/ps-extractor.sh /tmp/data-ps \
	&& rm /tmp/prestashop.zip

RUN docker-php-ext-install mysqli
ARG PRESTASHOP_CONTAINER_NAME=0
ARG PRESTASHOP_CONTAINER_DOMAIN=0
ARG PRESTASHOP_CONTAINER_SHOP_URL=0
ARG PRESTASHOP_CONTAINER_VERSION=0

ENV PRESTASHOP_CONTAINER_NAME=$PRESTASHOP_CONTAINER_NAME
ENV PRESTASHOP_CONTAINER_DOMAIN=$PRESTASHOP_CONTAINER_DOMAIN
ENV PRESTASHOP_CONTAINER_SHOP_URL=$PRESTASHOP_CONTAINER_SHOP_URL
ENV PRESTASHOP_CONTAINER_VERSION=$PRESTASHOP_CONTAINER_VERSION

COPY --chown=www-data:www-data ./wirecardpaymentgateway.zip /tmp
RUN unzip /tmp/wirecardpaymentgateway.zip -d /var/www/html/modules/
ADD wirecardpaymentgateway/tests/_data/ /var/www/html/_data