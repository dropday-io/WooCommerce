# wip
install:
	docker compose down --volumes \
	&& rm -r wp-plugins \
	&& mkdir -p ./wp-plugins/woocommerce \
	&& curl -L https://github.com/woocommerce/woocommerce/releases/download/nightly/woocommerce-trunk-nightly.zip -o ./woocommerce-trunk-nightly.zip \
	&& unzip ./woocommerce-trunk-nightly.zip -d ./wp-plugins \
	&& rm ./woocommerce-trunk-nightly.zip \
	&& docker compose up