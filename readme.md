# Download Spotify music tracks as mp3 through vk.com
Just searches for music track with the same name on vk.com, choosing the most likely the same track and downloads it.

### Prepare
* Create Standalone application: [https://vk.com/editapp?act=create](https://vk.com/editapp?act=create)
* Substitute Application id instead of APP_ID: `https://oauth.vk.com/authorize?client_id=APP_ID&redirect_uri=https://oauth.vk.com/blank.html&response_type=token&scope=audio`
* Take part of URL after `access_token=` and before `&expires_in` (namely `access_token` itself)
* Put that string into file `access_token.txt`

### Get tracks list
* In Spotify select tracks that you want to download
* Right click, then `Copy HTTP link` or `Copy Spotify URI` or `Copy Embed Code`
* Insert copied content into `spotify.txt`

### Run the process
Execute in terminal `php spotify-to-vk.php`

### Result
All tracks will be downloaded to `spotify` directory.

Output will contain download result, also files `spotify_fail.csv` and `spotify_success.csv` will contain information about successfully downloaded and failed tracks (can be opened as table with MS Office Excel or Libre/Open Office choosing `;` as values separator).

If there was internet connection error during downloading - you can copy content of `spotify_fail.csv` directly into `spotify.txt` and run the process again.

### License
MIT License (see license.txt)
