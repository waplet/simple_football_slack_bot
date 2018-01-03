# Usage

    cp .env.example .env

Populate your tokens and user ids

    composer install
    php scripts/create_db.php
    $ php -S localhost:80 index.php

Add bot to Slack bots

Invite to channel

    !help // Into slack channel

# Setup
    Host it
    
    Subscribe to `channel.message` event
    
    Enable events path to "{yoursite}/event"
    
    Same with actions "{yoursite}{route}"


# Requirements
 - php7+
 - sqlite
    
    
    sudo apt-get install php5-sqlite3
