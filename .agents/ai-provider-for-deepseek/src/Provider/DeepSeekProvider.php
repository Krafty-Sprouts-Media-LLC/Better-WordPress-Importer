<?php
/**
 * This file contains the definition of the DeepSeekProvider class.
 *
 * @package    Sajjad67\AiProviderForDeepSeek
 * @subpackage Sajjad67\AiProviderForDeepSeek/src
 * @author     Sajjad Hossain Sagor <sagorh672@gmail.com>
 */

declare(strict_types=1);

namespace Sajjad67\AiProviderForDeepSeek\Provider;

use Sajjad67\AiProviderForDeepSeek\Metadata\DeepSeekModelMetadataDirectory;
use Sajjad67\AiProviderForDeepSeek\Models\DeepSeekTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the WordPress AI Client provider for DeepSeek.
 *
 * @since 1.0.0
 */
class DeepSeekProvider extends AbstractApiProvider {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return 'https://api.deepseek.com/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since  1.0.0
	 * @param  ModelMetadata    $model_metadata    Metadata containing the specific model's configurations and capabilities.
	 * @param  ProviderMetadata $provider_metadata Metadata containing the provider's connection details.
	 * @throws RuntimeException                    If the model does not support any recognized generation capabilities.
	 * @return ModelInterface                      An instance of the specific model implementation.
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {
		$capabilities = $model_metadata->getSupportedCapabilities();

		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new DeepSeekTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_metadata_args = array(
			'deepseek',
			'DeepSeek',
			ProviderTypeEnum::cloud(),
			'https://platform.deepseek.com',
			RequestAuthenticationMethod::apiKey(),
		);

		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			// For WordPress, we should translate the description.
			if ( function_exists( '__' ) ) {
				$provider_metadata_args[] = __( 'Text generation with DeepSeek AI models.', 'ai-provider-for-deepseek' );
			} else {
				$provider_metadata_args[] = 'Text generation with DeepSeek AI models.';
			}
		}

		// Provider logoPath support was added in 1.3.0.
		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$provider_metadata_args[] = AIPRFD_AI_PROVIDER_FOR_DEEPSEEK_PLUGIN_DIR . 'assets/images/deepseek.svg';
		}

		return new ProviderMetadata( ...$provider_metadata_args );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check valid API access by attempting to list models.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new DeepSeekModelMetadataDirectory();
	}
}
