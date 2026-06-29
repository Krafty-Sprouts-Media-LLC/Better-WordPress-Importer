=== AI Provider For DeepSeek ===
Tags: ai, deepseek, gpt, artificial-intelligence, connector
Contributors: sajjad67
Author: Sajjad Hossain Sagor
Tested up to: 7.0
Requires at least: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Provider for DeepSeek for the PHP AI Client SDK.

== Description ==
AI Provider for DeepSeek integrates DeepSeek's AI models into WordPress as a provider for the PHP AI Client SDK. Once activated, DeepSeek is automatically registered as a provider — no manual configuration required.

**Key Features:**

**Text Generation:** Use DeepSeek's language models for content creation, summarization,
analysis, and more.

**Function Calling:** Connect AI responses to your site's internal functions for dynamic,
action-driven workflows.

**Web Search Support:** Allow AI agents to pull in real-time information for more accurate,
context-aware responses.

**Dynamic Model Discovery:** Available models are fetched directly from the DeepSeek API,
so your plugin stays compatible with new releases automatically.

**Requirements:**

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* DeepSeek API key

== Installation ==
To add a WordPress Plugin using the built-in plugin installer:

Go to Plugins > Add New.

1. Type in the name "AI Provider For DeepSeek" in Search Plugins box
2. Find the "AI Provider For DeepSeek" Plugin to install.
3. Click Install Now to begin the plugin installation.
4. The resulting installation screen will list the installation as successful or note any problems during the install.
If successful, click Activate Plugin to activate it, or Return to Plugin Installer for further actions.

To add a WordPress Plugin from GitHub repo / plugin zip file :
1. Go to WordPress plugin page
2. Click Add New & Upload Plugin
3. Drag / Click upload the plugin zip file
4. The resulting installation screen will list the installation as successful or note any problems during the install.
If successful, click Activate Plugin to activate it, or Return to Plugin Installer for further actions.

== Frequently Asked Questions ==
= How do I get a DeepSeek API key? =
Visit the [DeepSeek Platform](https://platform.deepseek.com/) to create an account and generate an API key.

== External Services ==
This plugin connects to the DeepSeek API (https://api.deepseek.com/v1) to provide AI capabilities within WordPress. It is required to enable text generation, function calling, web search, and dynamic model discovery features.

**What data is sent and when:**
- Your DeepSeek API key is sent with every request to authenticate with the service.
- Any text prompts, messages, or content you submit for AI processing are sent to DeepSeek's servers.
- A request is made to the DeepSeek API to fetch available models when the plugin initializes.
- Data is only transmitted when AI features are actively used (e.g., generating text, calling functions, or performing web searches).

**Service provider:** DeepSeek
- Website: [https://www.deepseek.com/](https://www.deepseek.com/)
- API Base URL: [https://api.deepseek.com/v1](https://api.deepseek.com/v1)
- Terms of Use: [https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html](https://cdn.deepseek.com/policies/en-US/deepseek-terms-of-use.html)
- Privacy Policy: [https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)
- Terms of Service: [https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html](https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html)

== Screenshots ==
1. Connectors Page.

== Changelog ==
= 1.0.0 =
- Initial release.

== Upgrade Notice ==
Always try to keep your plugin update so that you can get the improved and additional features added to this plugin up to date.