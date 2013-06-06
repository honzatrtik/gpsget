<?php

class UriPathRegexPostFetchFilter implements \VDB\Spider\Filter\PostFetchFilter
{

	protected $regex;

	public function __construct($regex)
	{
		$this->regex = $regex;
	}


	public function match(VDB\Spider\Resource $resource)
	{
		$uri = $resource->getUri();
		return preg_match($this->regex, $uri->getPath());
	}

}