<?php
/**
 * This file contains the definition of the DeepSeekModelMetadataDirectory class.
 *
 * @package    Sajjad67\AiProviderForDeepSeek
 * @subpackage Sajjad67\AiProviderForDeepSeek/src
 * @author     Sajjad Hossain Sagor <sagorh672@gmail.com>
 */

declare(strict_types=1);

namespace Sajjad67\AiProviderForDeepSeek\Metadata;

use Sajjad67\AiProviderForDeepSeek\Provider\DeepSeekProvider;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the model metadata directory used by the provider for DeepSeek.
 *
 * @since 1.0.0
 */
class DeepSeekModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {
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
		return new Request(
			$method,
			DeepSeekProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since  1.0.0
	 * @param  Response $response Response.
	 * @throws ResponseException  Response data not valid.
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		$response_data = $response->getData();
		if ( ! isset( $response_data['data'] ) || empty( $response_data['data'] ) ) {
			throw ResponseException::fromMissingData( 'DeepSeek', 'data' );
		}

		// Options shared by all text models.
		$base_text_options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$models_data = (array) $response_data['data'];

		$models = array_map(
			function ( array $model_data ) use ( $base_text_options ): ModelMetadata {
				$model_id = $model_data['id'];

				// Text models (Chat and Reasoner).
				$options = $base_text_options;
				if ( str_contains( $model_id, 'chat' ) ) {
					$options[] = new SupportedOption( OptionEnum::functionDeclarations() );
				}

				return new ModelMetadata(
					$model_id,
					self::formatDisplayName( $model_id ),
					array( CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory() ),
					$options
				);
			},
			$models_data
		);

		usort( $models, array( $this, 'modelSortCallback' ) );

		return $models;
	}

	/**
	 * Formats technical IDs into readable names.
	 *
	 * @since 1.0.0
	 * @param string $id ID.
	 */
	private static function formatDisplayName( string $id ): string {
		$map = array(
			'deepseek-chat'     => 'DeepSeek-V3 (Chat)',
			'deepseek-reasoner' => 'DeepSeek-R1 (Reasoner)',
		);

		return $map[ $id ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $id ) );
	}

	/**
	 * Callback function for sorting models.
	 *
	 * Sorts models: Chat > Reasoner > Others.
	 *
	 * @since  1.0.0
	 * @param  ModelMetadata $a First model.
	 * @param  ModelMetadata $b Second model.
	 * @return int              Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		$a_id = $a->getId();
		$b_id = $b->getId();

		// Pin Flagship models to the top.
		$priority = array(
			'deepseek-chat'     => 1,
			'deepseek-reasoner' => 2,
		);

		$a_priority = $priority[ $a_id ] ?? 99;
		$b_priority = $priority[ $b_id ] ?? 99;

		if ( $a_priority !== $b_priority ) {
			return $a_priority <=> $b_priority;
		}

		return strcmp( $a_id, $b_id );
	}
}
