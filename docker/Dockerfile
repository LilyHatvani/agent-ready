# Agent Ready: WordPress with an MCP door and agent levels, for Railway (or any Docker host).
# Build from the repo root:
#   docker build -f docker/Dockerfile -t agent-ready .
#
# What's in it (docs/decisions/0002-template-not-fork.md):
#   - the official WordPress image, pinned;
#   - the official MCP Adapter plugin, pinned by version and checksum;
#   - Agent Ready (this repository's plugin): agent users, levels, the guard;
#   - a must-use loader that keeps both on, whatever the database says.
# Core, plugins and themes come from the image on every start; only uploads live on the volume
# (docs/decisions/0004-what-lives-on-the-volume.md).

FROM wordpress:7.1.5-php8.4-apache

# The MCP Adapter's release zip (it carries its Composer dependencies). Bump the version and the checksum together.
ARG MCP_ADAPTER_VERSION=0.7.2
ARG MCP_ADAPTER_SHA256=baab734f26bd347a87947d141bb0728a8594ab7f17af03d2ae2e95e31d828b5b
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip; \
    curl -fsSL -o /tmp/mcp-adapter.zip \
      "https://github.com/WordPress/mcp-adapter/releases/download/v${MCP_ADAPTER_VERSION}/mcp-adapter.zip"; \
    echo "${MCP_ADAPTER_SHA256}  /tmp/mcp-adapter.zip" | sha256sum -c -; \
    unzip -q /tmp/mcp-adapter.zip -d /usr/src/wordpress/wp-content/plugins/; \
    rm /tmp/mcp-adapter.zip; \
    apt-get purge -y unzip; \
    rm -rf /var/lib/apt/lists/*

COPY plugin/agent-ready /usr/src/wordpress/wp-content/plugins/agent-ready
COPY docker/agent-ready-loader.php /usr/src/wordpress/wp-content/mu-plugins/agent-ready-loader.php
RUN chown -R www-data:www-data /usr/src/wordpress/wp-content

# Media goes to the trash like posts do, so an agent's "delete" is a trash it can't empty (guard.php).
# Code can't be changed from the dashboard: it would vanish on the next deploy, so the image is where it changes.
ENV WORDPRESS_CONFIG_EXTRA="define( 'MEDIA_TRASH', true ); define( 'DISALLOW_FILE_MODS', true );"
