<template>
    <div>
        <form @submit.prevent="submitCredentialForm" data-vv-scope="Credential-Form" class="Credential-Form">
            <md-card>
                <md-card-content>
                    <div class="md-layout md-gutter">
                        <div
                            class="md-layout-item  md-xlarge-size-100 md-large-size-100 md-medium-size-100 md-small-size-100">
                            <md-field
                                :class="{'md-invalid': errors.has('Credential-Form.api_url')}">
                                <label for="api_url">API Url</label>
                                <md-input
                                    id="api_url"
                                    name="api_url"
                                    v-model="credentialService.credential.apiUrl"
                                    v-validate="'required|min:3'"
                                />
                                <span
                                    class="md-error">{{ errors.first('Credential-Form.api_url') }}</span>
                            </md-field>
                        </div>
                        <div
                            class="md-layout-item  md-xlarge-size-100 md-large-size-100 md-medium-size-100 md-small-size-100">
                            <md-field
                                :class="{'md-invalid': errors.has('Credential-Form.authentication_token')}">
                                <label for="authentication_token">Authentication Token</label>
                                <md-input
                                    id="authentication_token"
                                    name="authentication_token"
                                    v-model="credentialService.credential.authenticationToken"
                                    v-validate="'required|min:3'"
                                />
                                <span class="md-error">{{ errors.first('Credential-Form.authentication_token') }}</span>
                            </md-field>
                        </div>

                    </div>
                </md-card-content>
                <md-progress-bar md-mode="indeterminate" v-if="loading"/>
                <md-card-actions>
                    <md-button class="md-raised md-primary" type="submit">Save</md-button>
                </md-card-actions>
            </md-card>

        </form>
    </div>
</template>

<script>
import { CredentialService } from '../../services/CredentialService'
import { EventBus } from '../../eventbus'
export default {
    name: 'Credential',

    data () {
        return {
            credentialService: new CredentialService(),
            loading:false,
        }
    },
    mounted () {
        this.getCredential()
    },
    methods: {
        async getCredential () {
            await this.credentialService.getCredential()
        },
        async submitCredentialForm () {
            let validator = await this.$validator.validateAll('Credential-Form')
            if (validator) {
                try {
                    this.loading = true
                    await this.credentialService.updateCredential()
                    EventBus.$emit('credentialUpdated')
                    this.loading = false
                    this.alertNotify('success', 'Credentials updated successfully.')

                } catch (e) {
                    this.loading = false
                    this.alertNotify('error', e.message)
                }
            }
        },
        alertNotify (type, message) {
            this.$notify({
                group: 'notify',
                type: type,
                title: type + ' !',
                text: message
            })
        },
    }
}
</script>

<style lang="scss" scoped>
    .md-card{
        height: 100% !important;
    }
    .Credential-Form{
        height: 100% !important;
    }
</style>
