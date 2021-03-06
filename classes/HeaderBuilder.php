<?php

namespace Zaxbux\SecurityHeaders\Classes;

use Url;
use Cache;
use Illuminate\Http\Response;
use Zaxbux\SecurityHeaders\Classes\HttpHeader;
use Zaxbux\SecurityHeaders\Classes\CSPFormBuilder;
use Zaxbux\SecurityHeaders\Models\CSPSettings;
use Zaxbux\SecurityHeaders\Models\HSTSSettings;
use Zaxbux\SecurityHeaders\Models\MiscellaneousHeaderSettings;
use Zaxbux\SecurityHeaders\Http\Controllers\ReportsController;
use Zaxbux\SecurityHeaders\Models\PermissionsPolicySettings;

class HeaderBuilder {

	const CACHE_KEY_CONTENT_SECURITY_POLICY   = "zaxbux_securityheaders_csp";
	const CACHE_KEY_STRICT_TRANSPORT_SECURITY = "zaxbux_securityheaders_hsts";
	const CACHE_KEY_PERMISSIONS_POLICY        = "zaxbux_securityheaders_permissions_policy";
	const CACHE_KEY_FEATURE_POLICY            = "zaxbux_securityheaders_feature_policy";
	const CACHE_KEY_REFERRER_POLICY           = "zaxbux_securityheaders_ref_policy";
	const CACHE_KEY_FRAME_OPTIONS             = "zaxbux_securityheaders_frame_options";
	const CACHE_KEY_CONTENT_TYPE_OPTIONS      = "zaxbux_securityheaders_content_type";
	const CACHE_KEY_XSS_PROTECTION            = "zaxbux_securityheaders_xss";
	const CACHE_KEY_REPORT_TO                 = "zaxbux_securityheaders_report_to";

	const CSP_REPORT_TO_GROUP = 'csp-endpoint';

	/**
	 * Add the Content-Security-Policy or Content-Security-Policy-Report-Only header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addContentSecurityPolicy(Response $response, $nonce) {
		$header = Cache::rememberForever(self::CACHE_KEY_CONTENT_SECURITY_POLICY, function () {
			if (!CSPSettings::get('enabled')) {
				return false;
			}

			return self::buildContentSecurityPolicyHeader();
		});

		if ($header) {
			$response->header($header->getName(), \sprintf($header->getValue(), $nonce));
		}
	}

	/**
	 * Add the Strict-Transport-Security header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addStrictTransportSecurity(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_STRICT_TRANSPORT_SECURITY, function () {
			if (!HSTSSettings::get('enabled')) {
				return false;
			}

			$value = sprintf('max-age=%d', HSTSSettings::get('max_age'));

			if (HSTSSettings::get('subdomains')) {
				$value .= '; includeSubDomains';
			}

			if (HSTSSettings::get('preload')) {
				$value .= '; preload';
			}

			return new HttpHeader('Strict-Transport-Security', $value);
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	/**
	 * Add the Referrer-Policy header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addReferrerPolicy(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_REFERRER_POLICY, function () {
			if ($value = MiscellaneousHeaderSettings::get('referrer_policy')) {
				return new HttpHeader('Referrer-Policy', $value);
			}

			return false;
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	/**
	 * Add the Frame-options header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addFrameOptions(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_FRAME_OPTIONS, function () {
			if ($value = MiscellaneousHeaderSettings::get('frame_options')) {
				return new HttpHeader('X-Frame-Options', $value);
			}

			return false;
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	/**
	 * Add the X-Content-Type-Options header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addContentTypeOptions(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_CONTENT_TYPE_OPTIONS, function () {
			if (MiscellaneousHeaderSettings::get('content_type_options', false) == true) {
				return new HttpHeader('X-Content-Type-Options', 'nosniff');
			}

			return false;
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	/**
	 * Add the X-XSS-Protection header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addXSSProtection(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_XSS_PROTECTION, function () {
			$value = MiscellaneousHeaderSettings::get('xss_protection');

			switch ($value) {
				case 'disable':
					$value = '0';
					break;
				case 'enable':
					$value = '1';
					break;
				case 'block':
					$value = '1; mode=block';
					break;
				default:
					return false;
			}

			return new HttpHeader('X-XSS-Protection', $value);
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	public static function addPermissionsPolicyHeader(Response $response): void {
		$header = Cache::rememberForever(self::CACHE_KEY_PERMISSIONS_POLICY, function (): ?HttpHeader {
			if (!PermissionsPolicySettings::get('enabled')) {
				return null;
			}

			$header = new HttpHeader('Permissions-Policy');

			if (PermissionsPolicySettings::get('report_only')) {
				$header->setName('Permissions-Policy-Report-Only');
			}

			$features = [];

			foreach (PermissionsPolicyFormBuilder::FEATURES as $feature) {
				$value = PermissionsPolicySettings::get(\str_replace('-', '_', $feature));

				if (!$value || (!$value['none'] && !$value['self'] && empty($value['origins']))) {
					continue;
				}

				$features[$feature] = [];
				
				if ($value['none'] == true) {
					continue;
				}

				if ($value['all'] == true) {
					$features[$feature][] = '*';
					continue;
				}

				if ($value['self'] == true) {
					$features[$feature][] = 'self';
				}

				foreach ($value['origins'] as $origin) {
					$features[$feature][] = \sprintf('"%s"', $origin['origin']);
				}
			}

			$policy = [];

			foreach ($features as $feature => $value) {
				$policy[] = \sprintf('%s=(%s)', $feature, implode(' ', $value));
			}

			$policy[] = trim(PermissionsPolicySettings::get('custom'));

			if (empty($policy)) {
				return null;
			}

			$header->setValue(implode(', ', $policy));

			return $header;
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	public static function addFeaturePolicyHeader(Response $response): void {
		$header = Cache::rememberForever(self::CACHE_KEY_FEATURE_POLICY, function (): ?HttpHeader {
			if (!MiscellaneousHeaderSettings::get('feature_policy')) {
				return null;
			}

			$header = new HttpHeader('Feature-Policy');

			if (MiscellaneousHeaderSettings::get('feature_policy_report_only')) {
				$header->setName('Feature-Policy-Report-Only');
			}

			$features = [];

			foreach (PermissionsPolicyFormBuilder::FEATURES as $feature) {
				$value = PermissionsPolicySettings::get(\str_replace('-', '_', $feature));

				if (!$value || (!$value['none'] && !$value['self'] && empty($value['origins']))) {
					continue;
				}

				$features[$feature] = [];
				
				if ($value['none'] == true) {
					$features[$feature][] = '\'none\'';
					continue;
				}

				if ($value['all'] == true) {
					$features[$feature][] = '*';
					continue;
				}

				if ($value['self'] == true) {
					$features[$feature][] = '\'self\'';
				}

				foreach ($value['origins'] as $origin) {
					$features[$feature][] = $origin['origin'];
				}
			}

			$policy = [];

			foreach ($features as $feature => $value) {
				$policy[] = \sprintf('%s %s', $feature, implode(' ', $value));
			}

			if (empty($policy)) {
				return null;
			}

			$header->setValue(implode('; ', $policy));

			return $header;
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	/**
	 * Add the X-XSS-Protection header to the response
	 *
	 * @param Illuminate\Http\Response
	 */
	public static function addReportTo(Response $response) {
		$header = Cache::rememberForever(self::CACHE_KEY_REPORT_TO, function () {
			if (!MiscellaneousHeaderSettings::get('report_to')) {
				return false;
			}

			$action = CSPSettings::get('report_only') ?
				ReportsController::ACTION_REPORT : ReportsController::ACTION_ENFORCE;

			$value = [
				'group' => self::CSP_REPORT_TO_GROUP,
				'max_age' => 2592000,
				'endpoints' => [
					['url' => Url::route('zaxbux.securityheaders.reports.csp_endpoint', ['action' => $action])]
				]
			];

			return new HttpHeader('Report-To', \json_encode($value));
		});

		if ($header) {
			$response->header($header->getName(), $header->getValue());
		}
	}

	private static function buildContentSecurityPolicyHeader() {
		$header = new HttpHeader('Content-Security-Policy');

		$directives = [];

		if (CSPSettings::get('report_only')) {
			$header->setName('Content-Security-Policy-Report-Only');
		}

		// Fetch directives, navigation directives, and base-uri directive
		$sourceBasedDirectives = ['base-uri'];

		foreach (CSPFormBuilder::CSP_DIRECTIVES['fetch'] as $directive) {
			$sourceBasedDirectives[] = $directive['name'];
		}

		foreach (CSPFormBuilder::CSP_DIRECTIVES['navigation'] as $directive) {
			$sourceBasedDirectives[] = $directive['name'];
		}

		foreach ($sourceBasedDirectives as $sourceBasedDirective) {
			if ($sourceData = CSPSettings::get(\str_replace('-', '_', $sourceBasedDirective))) {
				$directive = self::parseCSPDirectiveSources($sourceBasedDirective, $sourceData);

				if ($directive) {
					$directives[] = $directive;
				}
			}
		}

		// Plugin Types
		$pluginTypes = [];

		foreach (CSPSettings::get('plugin_types', []) as $typeGroup) {
			$pluginTypes[] = $typeGroup['value'];
		}

		if (count($pluginTypes) > 0) {
			$directives[] = sprintf('plugin-types %s;', \join(' ', $pluginTypes));
		}

		// Sandbox
		if ($sandbox = CSPSettings::get('sandbox')) {
			$directives[] = \sprintf('sandbox %s;', \implode(' ', $sandbox));
		}

		// Upgrade Insecure Requests
		if (CSPSettings::get('upgrade_insecure_requests')) {
			$directives[] = 'upgrade-insecure-requests;';
		}

		// Block All Mixed Content
		if (CSPSettings::get('block_all_mixed_content')) {
			$directives[] = 'block-all-mixed-content;';
		}

		// Policy violation logging
		if (CSPSettings::get('log_violations') == true) {
			$action = CSPSettings::get('report_only') ? 'report_only' : 'enforce';

			$reportUri = Url::route('zaxbux.securityheaders.reports.csp_endpoint', ['action' => $action]);

			$directives[] = \sprintf('report-uri %s;', $reportUri);
		}

		// Report-To support
		if (MiscellaneousHeaderSettings::get('report_to')) {
			$directives[] = \sprintf('report-to %s', self::CSP_REPORT_TO_GROUP);
		}

		if (count(array_filter($directives)) > 0) {
			return $header->setValue(\join(' ', $directives));
		}

		return false;
	}

	private static function parseCSPDirectiveSources($directive, $sourceData) {
		$sources = [];

		foreach ($sourceData as $source => $data) {
			// User-provided URIs and hashes
			if ($source == '_user_sources') {
				foreach ($data as $value) {
					if (!empty($value['value'])) {
						$sources[] = $value['value'];
					}
				}

				continue;
			}

			if ($source == 'nonce_source' && $data == true) {
				// %1$s is replaced with the nonce on every response
				$sources[] = "'nonce-%1\$s'";

				continue;
			}

			// For checkboxes
			if ($data == true) {
				$sources[] = \sprintf("'%s'", \str_replace('_', '-', $source));
			}
		}

		if (count($sources) > 0) {
			return \sprintf('%s %s;', $directive, \join(' ', $sources));
		}
	}
}
