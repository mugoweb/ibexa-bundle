<?php

namespace MugoWeb\IbexaBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MugoWebIbexaBundle extends Bundle
{
	public function getPath(): string
	{
		return \dirname(__DIR__);
	}
}