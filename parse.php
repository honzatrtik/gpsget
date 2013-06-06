<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\EventDispatcher\Event;
use VDB\Spider\Discoverer\XPathExpressionDiscoverer;
use VDB\Spider\Event\SpiderEvents;
use VDB\Spider\EventListener\PolitenessPolicyListener;
use VDB\Spider\Filter\Prefetch\AllowedHostsFilter;
use VDB\Spider\Filter\Prefetch\AllowedSchemeFilter;
use VDB\Spider\Filter\Prefetch\UriWithHashFragmentFilter;
use VDB\Spider\Filter\Prefetch\UriWithQueryStringFilter;
use VDB\Spider\Spider;
use Guzzle\Plugin\Cache\CachePlugin;
use Guzzle\Cache\DoctrineCacheAdapter;
use Doctrine\Common\Cache\PhpFileCache;


// The URI we want to start crawling with
$seed = 'http://www.obec.cr/';

// We want to allow all subdomains
$allowSubDomains = true;

// Create spider
$spider = new Spider($seed);

// Set some sane defaults for this example. We only visit the first level of www.dmoz.org. We stop at 10 queued resources
$spider->setMaxDepth(4);
$spider->setMaxQueueSize(100);

// We add an URI discoverer. Without it, the spider wouldn't get past the seed resource.
$spider->addDiscoverer(new XPathExpressionDiscoverer("//a"));

// Let's tell the spider to save all found resources on the filesystem
$spider->setPersistenceHandler(
	new \VDB\Spider\PersistenceHandler\FileSerializedResourcePersistenceHandler(__DIR__ . '/results')
);

// This time, we set the traversal algorithm to breadth-first. The default is depth-first
$spider->setTraversalAlgorithm(Spider::ALGORITHM_BREADTH_FIRST);

// Add some prefetch filters. These are executed before a resource is requested.
// The more you have of these, the less HTTP requests and work for the processors
$spider->addPreFetchFilter(new AllowedSchemeFilter(array('http')));
$spider->addPreFetchFilter(new AllowedHostsFilter(array($seed), $allowSubDomains));


// We add an eventlistener to the crawler that implements a politeness policy. We wait 450ms between every request to the same domain
$politenessPolicyEventListener = new PolitenessPolicyListener(200);
$spider->getDispatcher()->addListener(
	SpiderEvents::SPIDER_CRAWL_PRE_REQUEST,
	array($politenessPolicyEventListener, 'onCrawlPreRequest')
);

// Let's add a CLI progress meter for fun
$spider->getDispatcher()->addListener(
	SpiderEvents::SPIDER_CRAWL_PRE_ENQUEUE,
	function (Event $event) {
	}
);

//// Set up some caching, logging and profiling on the HTTP client of the spider
$guzzleClient = $spider->getRequestHandler()->getClient();
$cachePlugin = new CachePlugin(
	array(
		'adapter' => new DoctrineCacheAdapter(new PhpFileCache(__DIR__ . '/cache')),
		'default_ttl' => 0
	)
);
$guzzleClient->addSubscriber($cachePlugin);

// Set the user agent
$guzzleClient->setUserAgent('PHP-Spider');

// Execute the crawl
$result = $spider->crawl();

// Finally we could start some processing on the downloaded resources
$downloaded = $spider->getPersistenceHandler();
foreach ($downloaded as $resource)
{
	/* @var $resource \VDB\Spider\Resource */

	$regex = '%/([a-zA-Z\-])+/([0-9])+/?%';
	if (preg_match($regex, $resource->getUri()->getPath()))
	{
		$city = array();

		$crawler = $resource->getCrawler();

		$city['statut'] = $crawler->filterXpath('//div[@id="work"]//table//tr[1]//td[2]')->text();
		$city['nuts'] = $crawler->filterXpath('//div[@id="work"]//table//tr[7]//td[4]')->text();
		$city['district'] = $crawler->filterXpath('//div[@id="work"]//table//tr[7]//td[2]')->text();
		$city['city'] = $crawler->filterXpath('//title')->text();
		$city['lat'] = $crawler->filterXpath('//div[@id="work"]//table//tr[2]//td[2]')->text();
		$city['lng'] = $crawler->filterXpath('//div[@id="work"]//table//tr[3]//td[2]')->text();

		fputcsv(STDOUT, $city, ',', '"');
	}
}