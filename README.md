# steam-workshop-puller
Pulls out the workshop items you've installed for a given game

## How to use:
php workshop-pull.php "<your workshop enabled game name goes here here>"

## Theory of operation
The script downloads the steamapps list and searches for matching names, then it tests if there's a workshop option on the app's page.
After doing this it presents you with a list of workshop enabled steamapps (if two or more matching apps have workshop enabled).
After selecting the correct option (if need be) the script will proceed to download the workshop page for every workshop item found in the steam workshop folder in order to get its name.
After getting its name the workshop item gets copied into a folder named after the workshop item inside a folder named after the steamapp.
Upon finishing the script will create another folder named <steamapp_name>_REORGANIZED this folder will contain all the files of the workshop item without a parent forlder, this can be useful for some games.
