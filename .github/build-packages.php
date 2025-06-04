<?php

// Logic inspired from composer/metadata-minifier
function expandComposerMetadata(array $versions): array
{
    array_reduce($versions, function ($carry, $version) use (&$expandedVersions) {
        return $expandedVersions[] = array_filter(array_merge($carry, $version), fn ($v) => '__unset' !== $v);
    }, []);

    return $expandedVersions ?? [];
}

if (3 > $_SERVER['argc']) {
    echo "Usage: branch version dir1 dir2 ... dirN\n";
    exit(1);
}
chdir(dirname(__DIR__));

$dirs = $_SERVER['argv'];
array_shift($dirs);
$mergeBase = trim(shell_exec(sprintf('git merge-base "%s" HEAD', array_shift($dirs))));
$version = array_shift($dirs);

if ('8.0' === $version) {
    $version = '7.4'; // to be removed once deps allow ^8.0
}

$packages = [];
$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
$preferredInstall = json_decode(file_get_contents(__DIR__.'/composer-config.json'), true)['config']['preferred-install'];

foreach ($dirs as $k => $dir) {
    if (!system("git diff --name-only $mergeBase -- $dir", $exitStatus)) {
        if ($exitStatus) {
            exit($exitStatus);
        }
        unset($dirs[$k]);
        continue;
    }
    echo "$dir\n";

    $json = ltrim(file_get_contents($dir.'/composer.json'));
    if (null === $package = json_decode($json)) {
        passthru("composer validate $dir/composer.json");
        exit(1);
    }

    $package->repositories = [[
        'type' => 'composer',
        'url' => 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)).'/',
    ]];
    if (!str_contains($json, "\n    \"repositories\": [\n")) {
        $json = rtrim(json_encode(['repositories' => $package->repositories], $flags), "\n}").','.substr($json, 1);
        file_put_contents($dir.'/composer.json', $json);
    }

    if (isset($preferredInstall[$package->name]) && 'source' === $preferredInstall[$package->name]) {
        passthru("cd $dir && tar -cf package.tar --exclude='package.tar' *");
    } else {
        passthru("cd $dir && git init && git add . && git commit -q -m - && git archive -o package.tar HEAD && rm .git/ -Rf");
    }

    $package->version = preg_replace('/(?:\.x)?-dev$/', '', $package->extra->{'branch-alias'}->{'dev-main'} ?? $version).'.x-dev';
    $package->dist['type'] = 'tar';
    $package->dist['url'] = 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__))."/$dir/package.tar";

    $packages[$package->name][$package->version] = $package;

    foreach (['.json', '~dev.json'] as $ext) {
        $versions = @file_get_contents('https://repo.packagist.org/p2/'.$package->name.$ext) ?: '[]';
        $versions = json_decode($versions, true)['packages'][$package->name] ?? [];

        foreach (expandComposerMetadata($versions) as $p) {
            $packages[$package->name] += [$p['version'] => $p];
        }
    }
}

file_put_contents('packages.json', json_encode(compact('packages'), $flags));

if ($dirs) {
    $json = ltrim(file_get_contents('composer.json'));
    if (null === $package = json_decode($json)) {
        passthru("composer validate $dir/composer.json");
        exit(1);
    }

    $package->repositories[] = [
        'type' => 'composer',
        'url' => 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__)).'/',
    ];

    $json = preg_replace('/\n    "repositories": \[\n.*?\n    \],/s', '', $json);
    $json = rtrim(json_encode(['repositories' => $package->repositories], $flags), "\n}").','.substr($json, 1);
    file_put_contents('composer.json', $json);
}
