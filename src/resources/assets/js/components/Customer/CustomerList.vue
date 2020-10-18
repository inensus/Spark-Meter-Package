<template>
    <div>
        <widget id="meter-model-list"
                :title="title"
                :paginator="true"
                :paging_url="customerService.pagingUrl"
                :route_name="customerService.routeName"
                :show_per_page="true"
                :subscriber="subscriber"
                :callback="syncCustomers"
                button-text="Get Updates From Spark Meter"
                :button="true"
                :is-synced="isSynced"

        >
            <md-table v-model="customerService.list" md-sort="id" md-sort-order="asc" md-card>
                <md-table-row slot="md-table-row" slot-scope="{ item }" >
                    <md-table-cell md-label="ID"  md-sort-by="id"  >{{ item.id }}</md-table-cell>
                    <md-table-cell md-label="Spark ID" md-sort-by="sparkId" >{{ item.sparkId }}</md-table-cell>
                    <md-table-cell md-label="Name" md-sort-by="name">{{ item.name }}</md-table-cell>
                </md-table-row>
            </md-table>
            <md-progress-bar md-mode="indeterminate" v-if="loading"/>
        </widget>
    </div>
</template>

<script>
import Widget from '../Shared/Widget'
import { CustomerService } from '../../services/CustomerService'
import { EventBus } from '../../eventbus'
import { TariffService } from '../../services/TariffService'
import { MeterModelService } from '../../services/MeterModelService'

export default {
    name: 'CustomerList',
    components: { Widget },
    data () {
        return {
            customerService: new CustomerService(),
            tariffService:new TariffService(),
            meterModelService:new MeterModelService(),
            subscriber: 'customer-list',
            searchTerm: '',
            loading: false,
            isSynced:false,
            title:'Customers'
        }
    },
    mounted () {
        this.checkSync()
        EventBus.$on('pageLoaded', this.reloadList)
    },
    beforeDestroy () {
        EventBus.$off('pageLoaded', this.reloadList)
    },
    methods: {
        reloadList (subscriber, data) {
            if (subscriber !== this.subscriber) return
            this.customerService.updateList(data)
        },
        async checkSync () {
            try {
                this.loading = true
                this.isSynced = await this.customerService.checkCustomers()
                this.loading = false
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        async syncCustomers () {
            try {
                this.loading = true
                let metersSynced = await this.meterModelService.checkMeterModels()
                if (!metersSynced){
                    this.alertNotify('warn', 'MeterModels must be synchronized to synchronize Customers .')
                    this.loading = false
                    return
                }
                let tariffsSynced = await this.tariffService.checkTariffs()
                if (!tariffsSynced){
                    this.alertNotify('warn', 'Tariffs must be synchronized to synchronize Customers .')
                    this.loading = false
                    return
                }
                this.loading = true
                this.isSynced=false
                await this.customerService.syncCustomers()
                this.isSynced=true
                this.loading = false
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
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

<style scoped>

</style>
