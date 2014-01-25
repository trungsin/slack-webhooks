<?php

require_once __DIR__."/../vendor/autoload.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Guzzle\Http\Client;

$app = new Silex\Application();
$app->register(
    new DerAlex\Silex\YamlConfigServiceProvider(
        __DIR__ . '/../app/config/parameters.yml'
    )
);

/* Route for gitlab_push */
$app->post('/slack-proxy/gitlab-push', function(Request $request) use ($app) {
    $slackUrl = sprintf('https://%s.slack.com', $app['config']['slack']['team']);
    $content  = json_decode($request->getContent());
    $fields   = array();
    foreach ($content->commits as $key => $commit) {
        $fields[] = [
            'title' => $commit->message,
            'value' => sprintf(
                '<%s|%s> - %s',
                $commit->url,
                substr($commit->id, 0, 9),
                $commit->author->name
            ),
        ];
    }

    $message = sprintf(
        $app['config']['gitlab_push']['push_message'],
        $content->repository->homepage,
        $content->repository->name,
        $content->total_commits_count
    );

    $params = [
        'channel'  => '#'.$app['config']['gitlab_push']['channel'],
        'username' => $content->user_name,
        'fallback' => $message,
        'pretext' => $message,
        'fields'   => $fields,
        'color'    => $app['config']['gitlab_push']['color'],
    ];

    $client  = new Client($slackUrl);
    $request = $client->post(
        '/services/hooks/incoming-webhook?token='.$app['config']['gitlab_push']['token']
    );
    $request->setBody(['payload' => json_encode($params)], 'application/x-www-form-urlencoded');

    var_dump(['payload' => json_encode($params)]);

    $response = $request->send();

    return $response;
});

$app->run();
