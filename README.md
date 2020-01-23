## mediawiki-extensions-MachineVision

This extension will support collecting data about images from internal and
external machine vision services, storing it for on-wiki usage, and serving it
for editor verification.

For details, see the [project page on
mediawiki.org](https://www.mediawiki.org/wiki/Wikimedia_Product/Machine_vision_middleware).

### Installation

#### Download and enable the extension
1. Clone the extension repo into the `extensions` directory of your local
   MediaWiki installation:
   `git clone "https://gerrit.wikimedia.org/r/mediawiki/extensions/MachineVision"`
2. Run `composer install` in the MachineVision directory
3. Add `wfLoadExtension( 'MachineVision' );` to your `LocalSettings.php` file
4. Run `php maintenance/update.php` in your top-level MediaWiki directory

#### Import a mapping file
This extension will eventually support other providers, but for now testing and
development will be done using the Google Cloud Vision API. To map Freebase
objects (used by this API) to Wikidata entities (used by this extension),
download the Freebase/Wikidata Mappings file from Google at the below URL:

https://developers.google.com/freebase/#freebase-wikidata-mappings

Extract the downloaded file from its archive and place it somewhere that MediaWiki can access it. To
import, run the following maintenance script:

```
# in the mediawiki/extensions/MachineVision directory
php maintenance/populateFreebaseMapping.php --mappingFile path_to_your_file.nt
```

This process may take some time to complete.

### Configuration and Usage

#### Configuration
For local development, use the following settings in `LocalSettings.php` if using the Google Cloud
Vision API:

```php
$wgMachineVisionRequestLabelsOnUploadComplete = true;
$wgMachineVisionRequestLabelsFromWikidataPublicApi = true;
$wgMachineVisionGCVSendFileContents = true;

$wgMachineVisionHandlers['google'] = [
	'class' => MediaWiki\Extension\MachineVision\Handler\GoogleCloudVisionHandler::class,
	'services' => [
		'MachineVisionFetchGoogleCloudVisionAnnotationsJobFactory',
		'MachineVisionRepository',
		'MachineVisionRepoGroup',
		'MachineVisionDepictsSetter',
		'MachineVisionLabelResolver',
	],
];
```

If testing filtering criteria for the Computer-Aided Tagging feature, you may want to set some
SafeSearch limits:

```php
$wgMachineVisionGoogleSafeSearchLimits = [
	'adult' => 3,
	'medical' => 3,
	'violent' => 4,
	'racy' => 4,
];
```

#### Google API Credentials
To use the Google Cloud Vision with this extension, you will need to have valid
Google Cloud credentials. You will need to sign up for a free trial at:
https://console.cloud.google.com and generate credentials for the Google Cloud
Vision service. Download a JSON file with your credentials from the dashboard
and place it somewhere accessible to the web server that is running MediaWiki.
You will need to provide a path to this file as an extension configuration setting in
 `LocalSettings.php`:

```
$wgMachineVisionGoogleCredentialsFileLocation = '/var/www/mediawiki/machine-vision-credentials.json';
```

#### Usage
Log in and upload a file (after `$wgMachineVisionRequestLabelsOnUploadComplete`
has been set to `true`). This will enqueue an image annotation job in the MediaWiki job queue. After
 the job runs, navigate to `Special:SuggestedTags` to see suggested tags from Google.

##### Note
When and how often jobs run depends on your local configuration; see https://www.mediawiki.org/wiki/Manual:Job_queue for details.
 You can always run all jobs in the job queue manually by executing `maintenance/runJobs.php`.
