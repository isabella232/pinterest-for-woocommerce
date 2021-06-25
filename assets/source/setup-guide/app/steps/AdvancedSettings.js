/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CheckboxControl,
	Tooltip,
	Icon,
	__experimentalText as Text,
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepOverview from '../components/StepOverview';
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const AdvancedSettings = ( { view } ) => {
	const appSettings = useSettingsSelect();
	const setAppSettings = useSettingsDispatch( view === 'wizard' );
	const createNotice = useCreateNotice();

	const handleOptionChange = async ( name, value ) => {
		const update = await setAppSettings( {
			[ name ]: value ?? ! appSettings[ name ],
		} );

		if ( ! update.success ) {
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}
	};

	return (
		<div className="woocommerce-setup-guide__setup-product-sync">
			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Advanced Settings',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'Use description text to help users understand more',
							'pinterest-for-woocommerce'
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							{ undefined !== appSettings &&
							Object.keys( appSettings ).length > 0 ? (
								<>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Debug Logging',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Enable debug logging',
											'pinterest-for-woocommerce'
										) }
										help={
											<Tooltip text={ __(
												'Enable this option to enable logging of all plugin operations for debugging and diagnostics.',
												'pinterest-for-woocommerce'
											) }>
												<Icon icon="editor-help" />
											</Tooltip>
										}
										checked={
											appSettings.enable_debug_logging
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'enable_debug_logging'
											)
										}
									/>

									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Clear all settings',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Erase all plugin settings after plugin is uninstalled.',
											'pinterest-for-woocommerce'
										) }
										help={
											<Tooltip text={ __(
												'Enable this option if you are uninstalling the plugin and want to completely remove all settings.',
												'pinterest-for-woocommerce'
											) }>
												<Icon icon="editor-help" />
											</Tooltip>
										}
										checked={
											appSettings.erase_plugin_data
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'erase_plugin_data'
											)
										}
									/>
								</>
							) : (
								<Spinner />
							) }
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
};

export default AdvancedSettings;
