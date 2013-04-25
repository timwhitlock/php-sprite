# php-sprite

Command line image sprite generator in PHP.


## Usage

    # check it out
    git clone --recursive git://github.com/timwhitlock/php-sprite.git
    cd php-sprite

    # copy in some images to test
    mkdir images
    cp -pvr ~/some/pics/* images

    # run it
    php -d "open_basedir=`pwd`" -f sprite.php -- -d images > images/sprite.css
    
    # sprite is saved in images/sprite.png, css is piped to images/sprite.css
    ls -l images/sprite*
    
    # look at the other options
    php -d "open_basedir=`pwd`" -f sprite.php -- --help
