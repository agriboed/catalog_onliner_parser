# catalog.onliner.by Parser


Script allows to parse and import catalog items from catalog.onliner.by 
into your website (if needed) within JSON format. 

## How to use
* Download and install https://www.python.org/downloads/ on your system
* Download/generate catalog of items using Onliner API account page and save it as ./catalog.csv in script's with following format:

        category;manufacturer;id;model;url;price;any;any;any;

then save it as data.csv and put in root script category

* Modify settings in constants.py file (for ex. your website URL or secret key) if needed
* Run script in console using command

        $ python manage.py

* If you're going to re-save images from catalog, run script with flag:

        $ python manage.py image
        
* If you're going to immediately import data to your website use:

        $ python manage.py cms
       
* Full command should look like:

        $ python manage.py cms image


#### For OpenCart 3.0+
 * Upload files from /adapaters/opencart/
 * Modify secret key in the top of the import.php file. It must be the same as in constants.py