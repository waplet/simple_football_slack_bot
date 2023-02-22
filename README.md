# Usage

    cp .env.example .env

Populate your tokens and user ids

    composer install
    ls scripts | xargs -I {} php {}
    $ php -S localhost:80 index.php

Add bot to Slack bots

Invite to channel

    !help // Into slack channel

# Setup
Host it

    Need bot oAuth permissions:
        channels:history
        chat:write
        users:read

    Subscribe to `channel.message` event
    
    Enable events path to "{yoursite}/event"
    
    Same with actions "{yoursite}/action"

# Usage docker

    cp .env.example
    // update .env
    docker-compose up -d [--force-recreate --build]
    docker exec simple_football_slack_bot-app-1 bash -c 'ls scripts | xargs -I {} php scripts/{}'


# Requirements
 - php7+
 - sqlite
    
    
    sudo apt-get install php5-sqlite3
