<?php namespace Zaxbux\SecurityHeaders;

use Backend;
use System\Classes\PluginBase;
use Zaxbux\SecurityHeaders\Classes\CSPFormBuilder;
use Zaxbux\SecurityHeaders\Classes\PermissionsPolicyFormBuilder;

class Plugin extends PluginBase {

	const CSP_REPORT_URI = '/_/reports/csp-endpoint/{action}';

	/**
	 * @var bool Plugin requires elevated permissions.
	 * Necessary to alter headers on combined assets (/combine)
	 */
	public $elevated = true;

	public function boot() {
		/*
		 * Middleware
		 */
		$this->app['Illuminate\Contracts\Http\Kernel']->prependMiddleware(Classes\NonceGeneratorMiddleware::class);
		$this->app['Illuminate\Contracts\Http\Kernel']->pushMiddleware(Classes\SecurityHeaderMiddleware::class);

		/*
		 * Form Fields
		 */
		\Event::listen('backend.form.extendFields', function ($widget) {
			if (!$widget->getController() instanceof \System\Controllers\Settings) {
				return;
			}

			if (!$widget->model instanceof Models\CSPSettings) {
				return;
			}
			
			// Avoid adding fields to the repeater type fields
			if ($widget->isNested != false) {
				return;
			}

			$builder = new CSPFormBuilder;
			$builder->makeForm($widget);
		});

		\Event::listen('backend.form.extendFields', function (\Backend\Widgets\Form $widget) {
			if (!$widget->getController() instanceof \System\Controllers\Settings) {
				return;
			}

			if (!$widget->model instanceof Models\PermissionsPolicySettings) {
				return;
			}
			
			// Avoid adding fields to the repeater type fields
			if ($widget->isNested != false) {
				return;
			}

			$builder = new PermissionsPolicyFormBuilder;
			$builder->makeForm($widget);
		});
	}

	public function registerComponents() {
		return [
			Components\NonceProvider::class => Components\NonceProvider::SHORT_NAME,
		];
	}

	public function registerPermissions() {
		return [
			'zaxbux.securityheaders.access_settings' => [
				'label' => 'zaxbux.securityheaders::lang.permissions.access_settings',
				'tab'   => 'zaxbux.securityheaders::lang.plugin.name',
				'roles' => [
					'developer',
				]
			],
			'zaxbux.securityheaders.access_logs' => [
				'label' => 'zaxbux.securityheaders::lang.permissions.access_logs',
				'tab' => 'zaxbux.securityheaders::lang.plugin.name',
				'roles' => [
					'developer',
				]
			],
			'zaxbux.securityheaders.view_widgets' => [
				'label' => 'zaxbux.securityheaders::lang.permissions.view_widgets',
				'tab'   => 'zaxbux.securityheaders::lang.plugin.name',
				'roles' => [
					'developer',
				],
			],
		];
	}

	public function registerReportWidgets() {
		return [
			'Zaxbux\SecurityHeaders\ReportWidgets\CSPReports' => [
				'label'       => 'zaxbux.securityheaders::lang.report_widgets.csp_reports.label',
				'context'     => 'dashboard',
				'permissions' => [
					'zaxbux.securityheaders.view_widgets',
				],
			]
		];
	}

	public function registerSettings() {
		return [
			'csp' => [
				'label'       => 'zaxbux.securityheaders::lang.settings.csp.label',
				'description' => 'zaxbux.securityheaders::lang.settings.csp.description',
				'category'    => 'zaxbux.securityheaders::lang.settings.category',
				'icon'        => 'icon-shield',
				'class'       => Models\CSPSettings::class,
				'order'       => 500,
				'keywords'    => 'security headers csp',
				'permissions' => [
					'zaxbux.securityheaders.access_settings'
				],
			],
			'hsts' => [
				'label'       => 'zaxbux.securityheaders::lang.settings.hsts.label',
				'description' => 'zaxbux.securityheaders::lang.settings.hsts.description',
				'category'    => 'zaxbux.securityheaders::lang.settings.category',
				'icon'        => 'icon-shield',
				'class'       => Models\HSTSSettings::class,
				'order'       => 501,
				'keywords'    => 'security headers sts hsts',
				'permissions' => [
					'zaxbux.securityheaders.access_settings'
				],
			],
			'permissionsPolicy' => [
				'label'       => 'zaxbux.securityheaders::lang.settings.permissionsPolicy.label',
				'description' => 'zaxbux.securityheaders::lang.settings.permissionsPolicy.description',
				'category'    => 'zaxbux.securityheaders::lang.settings.category',
				'icon'        => 'icon-shield',
				'class'       => Models\PermissionsPolicySettings::class,
				'order'       => 502,
				'keywords'    => 'security headers feature-policy permissions-policy',
				'permissions' => [
					'zaxbux.securityheaders.access_settings'
				],
			],
			'miscellaneous' => [
				'label'       => 'zaxbux.securityheaders::lang.settings.miscellaneous.label',
				'description' => 'zaxbux.securityheaders::lang.settings.miscellaneous.description',
				'category'    => 'zaxbux.securityheaders::lang.settings.category',
				'icon'        => 'icon-shield',
				'class'       => Models\MiscellaneousHeaderSettings::class,
				'order'       => 503,
				'keywords'    => 'security headers',
				'permissions' => [
					'zaxbux.securityheaders.access_settings'
				],
			],
			'csp_logs' => [
				'label'       => 'zaxbux.securityheaders::lang.settings.csp_logs.label',
				'description' => 'zaxbux.securityheaders::lang.settings.csp_logs.description',
				'category'    => 'zaxbux.securityheaders::lang.settings.category',
				'icon'        => 'icon-shield',
				'url'         => Backend::url('zaxbux/securityheaders/csplogs'),
				'order'       => 504,
				'keywords'    => 'security headers csp',
				'permissions' => [
					'zaxbux.securityheaders.access_settings'
				],
			],
		];
	}

	public function register() {
		/*
		 * Register console commands
		 */
		$this->registerConsoleCommand('zaxbux.securityheaders.disable_csp', Console\DisableCSPCommand::class);
		$this->registerConsoleCommand('zaxbux.securityheaders.disable_hsts', Console\DisableHSTSCommand::class);
	}
}
