<template>
    <widget id="tariff-list"
            :title="title"
            :paginator="true"
            :paging_url="tariffService.pagingUrl"
            :route_name="tariffService.routeName"
            :show_per_page="true"
            :subscriber="subscriber"
            :callback="syncTariffs"
            button-text="Get Updates From Spark Meter"
            :button="true"
            :is-synced="isSynced"
            :loading="loading"
    >
        <md-table v-model="tariffService.list" md-sort="id" md-sort-order="asc" md-card>
            <md-table-row slot="md-table-row" slot-scope="{ item }">
                <md-table-cell md-label="ID" md-sort-by="id" >{{ item.id }}</md-table-cell>
                <md-table-cell md-label="Name" md-sort-by="name">{{ item.name }}</md-table-cell>
                <md-table-cell md-label="Flat Price" md-sort-by="price">{{ item.price}}</md-table-cell>
                <md-table-cell md-label="Flat Load Limit" md-sort-by="flat_load_limit">{{ item.flatLoadLimit }}</md-table-cell>
                <md-table-cell md-label="#">
                    <md-button class="md-icon-button" @click="editTariff(item.tariffId)">
                        <md-tooltip md-direction="top">Edit</md-tooltip>
                        <md-icon>edit</md-icon>
                    </md-button>
                </md-table-cell>
            </md-table-row>
        </md-table>
        <md-progress-bar md-mode="indeterminate" v-if="loading"/>
    </widget>
</template>

<script>
import Widget from '../Shared/Widget'
import { EventBus } from '../../eventbus'
import { TariffService } from '../../services/TariffService'
import { MeterModelService } from '../../services/MeterModelService'
export default {
    name: 'TariffList',
    components: { Widget },
    data () {
        return {
            tariffService: new TariffService(),
            meterModelService:new MeterModelService(),
            subscriber: 'tariff-list',
            searchTerm: '',
            loading: false,
            isSynced:false,
            title:'Tariffs'
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
            this.tariffService.updateList(data)
        },
        async checkSync () {
            try {
                this.loading = true
                this.isSynced = await this.tariffService.checkTariffs()
                this.loading = false
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        async syncTariffs () {
            try {
                this.loading = true
                let metersSynced = await this.meterModelService.checkMeterModels()
                if (!metersSynced){
                    this.alertNotify('warn', 'MeterModels must be updated to update Tariffs.')
                    return
                }
                this.isSynced=false
                await this.tariffService.syncTariffs()
                this.isSynced=true
                this.loading = false
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        editTariff(tariffId){
            this.$router.push({ path: '/spark-meters/sm-tariff/' + tariffId })
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
