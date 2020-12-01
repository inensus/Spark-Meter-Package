<template>
    <div>
        <widget id="customer-list"
                :title="title"
                :paginator="true"
                :paging_url="customerService.pagingUrl"
                :route_name="customerService.routeName"
                :show_per_page="true"
                :subscriber="subscriber"
                color="green"
                @widgetAction="syncCustomers()"
                :button="true"
                buttonIcon="cloud_download"
                :button-text="buttonText"
                :emptyStateLabel="label"
                :emptyStateButtonText="buttonText"
                :newRecordButton="false"
        >

            <md-table v-model="customerService.list" md-sort="id" md-sort-order="asc" md-card>
                <md-table-row slot="md-table-row" slot-scope="{ item }">
                    <md-table-cell md-label="ID" md-sort-by="id">{{ item.id }}</md-table-cell>
                    <md-table-cell md-label="Spark ID" md-sort-by="sparkId">{{ item.sparkId }}</md-table-cell>
                    <md-table-cell md-label="Name" md-sort-by="name">{{ item.name }}</md-table-cell>
                </md-table-row>
            </md-table>

        </widget>
        <md-progress-bar md-mode="indeterminate" v-if="loading"/>
        <redirection :redirection-url="redirectionUrl" :dialog-active="redirectDialogActive"
                     :message="redirectionMessage"/>
    </div>
</template>

<script>
import Widget from '../Shared/Widget'
import Redirection from '../Shared/Redirection'
import { CustomerService } from '../../services/CustomerService'
import { EventBus } from '../../eventbus'
import { TariffService } from '../../services/TariffService'
import { MeterModelService } from '../../services/MeterModelService'
import { SystemService } from '../../services/SystemService'

export default {
    name: 'CustomerList',
    components: { Widget, Redirection },
    data () {
        return {
            systemService: new SystemService(),
            customerService: new CustomerService(),
            tariffService: new TariffService(),
            meterModelService: new MeterModelService(),
            subscriber: 'customer-list',
            searchTerm: '',
            loading: false,
            isSynced: false,
            title: 'Customers',
            redirectionUrl: '/spark-meters/sm-overview',
            redirectDialogActive: false,
            buttonText: 'Get Updates From Spark Meter',
            label: 'Customer Records Not Up to Date.',
            redirectionMessage: 'Please make your location settings first.'

        }
    },
    mounted () {
        this.checkLocation()
        EventBus.$on('pageLoaded', this.reloadList)
    },
    beforeDestroy () {
        EventBus.$off('pageLoaded', this.reloadList)
    },
    methods: {
        async getSystem () {
            try {
                await this.systemService.getSystemInfo()
                await this.checkSync()
            } catch (e) {
                this.redirectionMessage = 'API credentials not authenticated.'
                this.redirectDialogActive = true
            }
        },
        async checkLocation () {
            let response = await this.customerService.checkLocation()
            if (response.length === 0) {
                this.redirectionUrl = '/locations/add-cluster'
                this.redirectDialogActive = true
            } else {
                await this.checkConnectionTypes()
            }
        },
        async checkConnectionTypes () {
            let response = await this.customerService.checkConnectionTypes()
            if (!response.type) {
                this.redirectionUrl = '/connection-types'
                this.redirectionMessage = 'Please create a Connection Type.'
                this.redirectDialogActive = true
            } else if (!response.group) {
                this.redirectionUrl = '/connection-groups'
                this.redirectionMessage = 'Please create a Connection Group.'
                this.redirectDialogActive = true
            } else {
                await this.getSystem()
            }
        },
        async syncCustomers () {
            if (!this.loading) {
                try {
                    this.loading = true
                    let metersSynced = await this.meterModelService.checkMeterModels()
                    if (!metersSynced) {
                        this.alertNotify('warn', 'MeterModels must be synchronized to synchronize Customers .')
                        this.loading = false
                        return
                    }
                    let tariffsSynced = await this.tariffService.checkTariffs()
                    if (!tariffsSynced) {
                        this.alertNotify('warn', 'Tariffs must be synchronized to synchronize Customers .')
                        this.loading = false
                        return
                    }
                    this.loading = true
                    this.isSynced = false
                    await this.customerService.syncCustomers()
                    EventBus.$emit('widgetContentLoaded', this.subscriber, 1)
                    this.isSynced = true
                    this.loading = false
                } catch (e) {
                    this.loading = false
                    this.alertNotify('error', e.message)
                    EventBus.$emit('widgetContentLoaded', this.subscriber, 0)
                }
            }

        },
        async checkSync () {
            try {
                this.loading = true
                this.isSynced = await this.customerService.checkCustomers()
                this.loading = false
                if (!this.isSynced) {
                    let swalOptions = {
                        title: 'Updates',
                        showCancelButton: true,
                        text: 'Customer Records Not Up to Date.',
                        confirmButtonText: 'Update',
                        cancelButtonText: 'Cancel',
                    }
                    this.$swal(
                        swalOptions
                    ).then((result) => {
                        if (result.value) {
                            this.syncCustomers()
                        }
                    })
                }
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        reloadList (subscriber, data) {
            if (subscriber !== this.subscriber) return
            this.customerService.updateList(data)
            EventBus.$emit('widgetContentLoaded', this.subscriber, this.customerService.list.length)
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

<style scoped>

</style>
