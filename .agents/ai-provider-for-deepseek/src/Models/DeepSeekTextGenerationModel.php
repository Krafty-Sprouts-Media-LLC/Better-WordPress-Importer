<?php
/**
 * This file contains the definition of the DeepSeekTextGenerationModel class.
 *
 * @package    Sajjad67\AiProviderForDeepSeek
 * @subpackage Sajjad67\AiProviderForDeepSeek/src
 * @author     Sajjad Hossain Sagor <sagorh672@gmail.com>
 */

declare( strict_types=1 );

namespace Sajjad67\AiProviderForDeepSeek\Models;

use Sajjad67\AiProviderForDeepSeek\Provider\DeepSeekProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for an DeepSeek text generation model using the OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class DeepSeekTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	/**
	 * {@inheritDoc}
	 *
	 * @since  1.0.0
	 * @param  HttpMethodEnum $method  The HTTP method to use for the request.
	 * @param  string         $path    The API endpoint path (e.g., 'v1/models').
	 * @param  array          $headers Optional. Array of HTTP headers. Default empty array.
	 * @param  mixed          $data    Optional. The data to be sent in the request body. Default null.
	 * @return Request                 The constructed Request object.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		// DeepSeek supports OpenAI-compatible endpoints at /v1/.
		return new Request(
			$method,
			DeepSeekProvider::url( $path ),
			$headers,
			$data,
			$this->getRequestOptions()
		);
	}
}
