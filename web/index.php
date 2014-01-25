<?php
require_once __DIR__."/../vendor/autoload.php";

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Guzzle\Http\Client;

$app = new Silex\Application();
$app->register(new DerAlex\Silex\YamlConfigServiceProvider(__DIR__ . '/../app/config/parameters.yml'));

$app->post('/slack-proxy/code-push', function(Request $request) use ($app) {

    $slackUrl = $app['config']['slack_url'];
    $content    = json_decode($request->getContent());

    /* Add foreach commits in GitLab Web Hooks an attachment to the message */
    $fields     = array();
    foreach ($content->commits as $key => $commit) {
        $fields[] = [ 'title' => $commit->message,
                      'value' => sprintf('<%s|%s> - %s
',                               $commit->url,
                                 substr($commit->id, 0, 9),
                                 $commit->author->name
                                 ),
                    ];
    }

    $message = sprintf('New push on <%s|%s> (%d Commits):
',
        $content->repository->homepage,
        $content->repository->name,
        $content->total_commits_count
    );

    $params = [
            'channel'  => '#'.$app['config']['gitlab']['channel'],
            'username' => $content->user_name,
            "fallback" => $message,
            'text'     => $message,
            'fields'   => $fields,
            'color'    => $app['config']['gitlab']['color']
    ];

    $client = new Client($slackUrl);
    $request = $client->post('/services/hooks/incoming-webhook?token='.$app['config']['gitlab']['token']);
    $request->setBody(json_encode($params), 'applciation/json');

    $response = $request->send();

    return $response;
});

$app->run();
