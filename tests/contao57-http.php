<?php
$project=getenv('AURIGA_TEST_PROJECT');
if (!$project || !str_contains((string)getenv('DATABASE_URL'), '/auriga57_test_')) throw new RuntimeException('Use an isolated auriga57_test_* database');
$fixtures=json_decode(file_get_contents($project.'/var/auriga-http-fixtures.json'),true,512,JSON_THROW_ON_ERROR);
if (!isset($argv[1])) {
    foreach (array_keys($fixtures) as $index) {
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.(int)$index,$status);
        if ($status !== 0) exit($status);
    }
    exit(0);
}
$fixture=$fixtures[(int)$argv[1]];
require $project.'/vendor/autoload.php';
$kernel=Contao\ManagerBundle\HttpKernel\ContaoKernel::fromInput($project,new Symfony\Component\Console\Input\ArrayInput(['--env'=>'prod']));
$request=Symfony\Component\HttpFoundation\Request::create($fixture['url']);
$response=$kernel->handle($request);
if ($response->getStatusCode()!==200 || !str_contains($response->getContent(),$fixture['text'])) {
    fwrite(STDERR,'Unexpected HTTP status '.$response->getStatusCode().' for '.$fixture['url']."\n"); exit(1);
}
$kernel->terminate($request,$response);
echo 'OK: HTTP 200 '.$fixture['url']."\n";
