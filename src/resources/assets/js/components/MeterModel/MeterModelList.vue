<template>
    <div>
        <widget id="meter-model-list"
                :title="title"
                :paginator="true"
                :paging_url="meterModelService.pagingUrl"
                :route_name="meterModelService.routeName"
                :show_per_page="true"
                :subscriber="subscriber"
                :callback="syncMeterModels"
                button-text="Get Updates From Spark Meter"
                :button="true"
                :is-synced="isSynced"
                :loading="loading"

        >
            <md-table v-model="meterModelService.list" md-sort="id" md-sort-order="asc" md-card>
                <md-table-row slot="md-table-row" slot-scope="{ item }">
                    <md-table-cell md-label="ID" md-sort-by="id" >{{ item.id }}</md-table-cell>
                    <md-table-cell md-label="Name" md-sort-by="model_name">{{ item.modelName }}</md-table-cell>
                    <md-table-cell md-label="Continuous Limit" md-sort-by="continuous_limit">{{ item.continuousLimit}}
                    </md-table-cell>
                    <md-table-cell md-label="Inrush Limit" md-sort-by="inrush_limit">{{ item.inrushLimit }}
                    </md-table-cell>
                </md-table-row>
            </md-table>
            <md-progress-bar md-mode="indeterminate"  v-if="loading"/>
        </widget>
    </div>
</template>

<script>
import Widget from '../Shared/Widget'
import { MeterModelService } from '../../services/MeterModelService'
import { EventBus } from '../../eventbus'

export default {
    name: 'MeterModelList',
    components: { Widget },
    data () {
        return {
            meterModelService: new MeterModelService(),
            subscriber: 'meter-model-list',
            searchTerm: '',
            loading: false,
            isSynced:false,
            title:'Meter Models'
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
            this.meterModelService.updateList(data)
        },
        async checkSync () {
            try {
                this.loading = true
                this.isSynced = await this.meterModelService.checkMeterModels()
                this.loading = false
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        async syncMeterModels () {
            try {
                this.loading = true
                this.isSynced=false
                await this.meterModelService.syncMeterModels()
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
