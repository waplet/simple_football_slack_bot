# Usage

    cp .env.example .env

Populate your tokens and user ids

    composer install
    php scripts/create_db.php
    ($ chmod +x ./run.sh) // Optional if not runnable
    $ ./run.sh

Add bot to Slack bots

Invite to channel

    !help // Into slack channel
# Requirements
 - php7+
 - sqlite
    
    
    sudo apt-get install php5-sqlite3