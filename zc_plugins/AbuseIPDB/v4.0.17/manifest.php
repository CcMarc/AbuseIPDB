<?php
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

return [
    'pluginVersion'       => 'v4.0.17',
    'pluginName' => "AbuseIPDB",
    'pluginDescription' => 'Protect your e-commerce website from abusive IP addresses',
    'pluginAuthor' => 'marcopolo',
    'pluginId' => 2371, // ID from Zen Cart forum
    'zcVersions' => ['v222'],
    'changelog' => 'https://github.com/CcMarc/AbuseIPDB/blob/main/CHANGELOG.md',
    'github_repo' => 'https://github.com/CcMarc/AbuseIPDB', // url
    'pluginGroups' => [],
];
