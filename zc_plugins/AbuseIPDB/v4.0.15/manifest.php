<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

return [
    'pluginVersion'       => 'v4.0.15',
    'pluginName' => "AbuseIPDB",
    'pluginDescription' => 'Protect your e-commerce website from abusive IP addresses',
    'pluginAuthor' => 'marcopolo',
    'pluginId' => 2371, // ID from Zen Cart forum
    'zcVersions' => ['v222'],
    'changelog' => '', // online URL (eg github release tag page, or changelog file there) or local filename only, ie: changelog.txt (in same dir as this manifest file)
    'github_repo' => 'https://github.com/CcMarc/AbuseIPDB', // url
    'pluginGroups' => [],
];
