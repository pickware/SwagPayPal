import template from './swag-paypal.html.twig';
import './swag-paypal.scss';
import constants from './swag-paypal-consts';

const { Mixin, Defaults } = Shopware;
const { Criteria } = Shopware.Data;
const { hasOwnProperty } = Shopware.Utils.object;

Shopware.Component.register('swag-paypal', {
    template,

    inject: [
        'SwagPayPalWebhookRegisterService',
        'SwagPayPalApiCredentialsService',
        'SwagPaypalPaymentMethodServiceService',
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isTestSuccessful: false,
            isTestSandboxSuccessful: false,
            clientIdFilled: false,
            clientSecretFilled: false,
            clientIdSandboxFilled: false,
            clientSecretSandboxFilled: false,
            sandboxChecked: false,
            salesChannels: [],
            config: null,
            isSetDefaultPaymentSuccessful: false,
            isSettingDefaultPaymentMethods: false,
            savingDisabled: false,
            initialConfigValueSandbox: false,
            initialConfigValueWebhookId: null,
            initialConfigValueWebhookExecuteToken: null,
            messageBlankErrorState: null,
            ...constants
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        showSPBCard() {
            if (hasOwnProperty(this.config, 'SwagPayPal.settings.merchantLocation') &&
                    this.config['SwagPayPal.settings.merchantLocation'] !== null
            ) {
                return this.config['SwagPayPal.settings.merchantLocation'] === this.MERCHANT_LOCATION_OTHER;
            }

            const defaultConfig = this.$refs.configComponent.allConfigs.null;

            return defaultConfig['SwagPayPal.settings.merchantLocation'] === this.MERCHANT_LOCATION_OTHER;
        },

        showPlusCard() {
            if (hasOwnProperty(this.config, 'SwagPayPal.settings.merchantLocation') &&
                    this.config['SwagPayPal.settings.merchantLocation'] !== null
            ) {
                return this.config['SwagPayPal.settings.merchantLocation'] === this.MERCHANT_LOCATION_GERMANY;
            }

            const defaultConfig = this.$refs.configComponent.allConfigs.null;

            return defaultConfig['SwagPayPal.settings.merchantLocation'] === this.MERCHANT_LOCATION_GERMANY;
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        clientIdErrorState() {
            if (this.sandboxChecked || this.clientIdFilled) {
                return null;
            }

            return this.messageBlankErrorState;
        },

        clientSecretErrorState() {
            if (this.sandboxChecked || this.clientSecretFilled) {
                return null;
            }

            return this.messageBlankErrorState;
        },

        clientIdSandboxErrorState() {
            if (!this.sandboxChecked || this.clientIdSandboxFilled) {
                return null;
            }

            return this.messageBlankErrorState;
        },

        clientSecretSandboxErrorState() {
            if (!this.sandboxChecked || this.clientSecretSandboxFilled) {
                return null;
            }

            return this.messageBlankErrorState;
        },

        hasError() {
            return (!this.sandboxChecked && !(this.clientIdFilled && this.clientSecretFilled)) ||
                (this.sandboxChecked && !(this.clientIdSandboxFilled && this.clientSecretSandboxFilled));
        }
    },

    watch: {
        config: {
            handler() {
                const defaultConfig = this.$refs.configComponent.allConfigs.null;
                const salesChannelId = this.$refs.configComponent.selectedSalesChannelId;

                if (salesChannelId === null) {
                    this.clientIdFilled = !!this.config['SwagPayPal.settings.clientId'];
                    this.clientSecretFilled = !!this.config['SwagPayPal.settings.clientSecret'];
                    this.clientIdSandboxFilled = !!this.config['SwagPayPal.settings.clientIdSandbox'];
                    this.clientSecretSandboxFilled = !!this.config['SwagPayPal.settings.clientSecretSandbox'];
                    this.sandboxChecked = !!this.config['SwagPayPal.settings.sandbox'];
                } else {
                    this.clientIdFilled = !!this.config['SwagPayPal.settings.clientId']
                        || !!defaultConfig['SwagPayPal.settings.clientId'];
                    this.clientSecretFilled = !!this.config['SwagPayPal.settings.clientSecret']
                        || !!defaultConfig['SwagPayPal.settings.clientSecret'];
                    this.clientIdSandboxFilled = !!this.config['SwagPayPal.settings.clientIdSandbox']
                        || !!defaultConfig['SwagPayPal.settings.clientIdSandbox'];
                    this.clientSecretSandboxFilled = !!this.config['SwagPayPal.settings.clientSecretSandbox']
                        || !!defaultConfig['SwagPayPal.settings.clientSecretSandbox'];
                    this.sandboxChecked = !!this.config['SwagPayPal.settings.sandbox']
                        || !!defaultConfig['SwagPayPal.settings.sandbox'];
                }

                if (this.initialConfigValueSandbox !== this.config['SwagPayPal.settings.sandbox']) {
                    // Reset webhook settings if the sandbox mode is switched,
                    // so it will not try to register the webhook with the wrong scoped webhookId (INVALID_RESOURCE_ID)
                    this.config['SwagPayPal.settings.webhookId'] = null;
                    this.config['SwagPayPal.settings.webhookExecuteToken'] = null;
                } else {
                    this.config['SwagPayPal.settings.webhookId'] = this.initialConfigValueWebhookId;
                    this.config['SwagPayPal.settings.webhookExecuteToken'] = this.initialConfigValueWebhookExecuteToken;
                }
            },
            deep: true
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            const criteria = new Criteria();
            criteria.addFilter(Criteria.equalsAny('typeId', [
                Defaults.storefrontSalesChannelTypeId,
                Defaults.apiSalesChannelTypeId
            ]));

            this.salesChannelRepository.search(criteria, Shopware.Context.api).then(res => {
                res.add({
                    id: null,
                    translated: {
                        name: this.$tc('sw-sales-channel-switch.labelDefaultOption')
                    }
                });

                this.salesChannels = res;
            }).finally(() => {
                this.isLoading = false;
            });

            this.messageBlankErrorState = {
                code: 1,
                detail: this.$tc('swag-paypal.messageNotBlank')
            };
        },

        onSave() {
            if (this.hasError) {
                return;
            }

            this.save();
        },

        onInitialInput(config) {
            this.initialConfigValueSandbox = config['SwagPayPal.settings.sandbox'];
            this.initialConfigValueWebhookId = config['SwagPayPal.settings.webhookId'];
            this.initialConfigValueWebhookExecuteToken = config['SwagPayPal.settings.webhookExecuteToken'];
        },

        save() {
            this.isLoading = true;

            this.$refs.configComponent.save().then((res) => {
                this.isLoading = false;
                this.isSaveSuccessful = true;

                if (res) {
                    this.config = res;
                }

                this.registerWebhook();
            }).catch(() => {
                this.isLoading = false;
            });
        },

        registerWebhook() {
            this.SwagPayPalWebhookRegisterService.registerWebhook(this.$refs.configComponent.selectedSalesChannelId)
                .then((response) => {
                    const result = response.result;

                    if (result === this.WEBHOOK_RESULT_NOTHING) {
                        return;
                    }

                    if (result === this.WEBHOOK_RESULT_CREATED) {
                        this.createNotificationSuccess({
                            title: this.$tc('global.default.success'),
                            message: this.$tc('swag-paypal.settingForm.messageWebhookCreated')
                        });

                        return;
                    }

                    if (result === this.WEBHOOK_RESULT_UPDATED) {
                        this.createNotificationSuccess({
                            title: this.$tc('global.default.success'),
                            message: this.$tc('swag-paypal.settingForm.messageWebhookUpdated')
                        });
                    }
                    this.isLoading = false;
                }).catch((errorResponse) => {
                    if (errorResponse.response.data && errorResponse.response.data.errors) {
                        let message = `${this.$tc('swag-paypal.settingForm.messageWebhookError')}<br><br><ul>`;
                        errorResponse.response.data.errors.forEach((error) => {
                            message = `${message}<li>${error.detail}</li>`;
                        });
                        message += '</li>';
                        this.createNotificationError({
                            title: this.$tc('swag-paypal.settingForm.titleError'),
                            message: message
                        });
                    }
                    this.isLoading = false;
                });
        },

        /**
         * @deprecated tag:v2.0.0 - will be removed
         */
        setErrorStates() {
        },

        onSetPaymentMethodDefault() {
            this.isSettingDefaultPaymentMethods = true;

            this.SwagPaypalPaymentMethodServiceService.setDefaultPaymentForSalesChannel(
                this.$refs.configComponent.selectedSalesChannelId
            ).then(() => {
                this.isSettingDefaultPaymentMethods = false;
                this.isSetDefaultPaymentSuccessful = true;
            });
        },

        preventSave(mode) {
            if (!mode) {
                this.savingDisabled = false;
                return;
            }

            this.savingDisabled = true;
        }
    }
});
