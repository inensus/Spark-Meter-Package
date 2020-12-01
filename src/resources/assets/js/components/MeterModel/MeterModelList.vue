<template>
    <div>

        <widget id="meter-model-list"
                :title="title"
                :paginator="true"
                :paging_url="meterModelService.pagingUrl"
                :route_name="meterModelService.routeName"
                :show_per_page="true"
                :subscriber="subscriber"
                color="green"
                @widgetAction="syncMeterModels()"
                :button="true"
                buttonIcon="cloud_download"
                :button-text="buttonText"
                :emptyStateLabel="label"
                :emptyStateButtonText="buttonText"
                :newRecordButton="false"
        >

            <md-table v-model="meterModelService.list" md-sort="id" md-sort-order="asc" md-card>
                <md-table-row slot="md-table-row" slot-scope="{ item }">
                    <md-table-cell md-label="ID" md-sort-by="id">{{ item.id }}</md-table-cell>
                    <md-table-cell md-label="Name" md-sort-by="model_name">{{ item.modelName }}</md-table-cell>
                    <md-table-cell md-label="Continuous Limit" md-sort-by="continuous_limit">{{ item.continuousLimit}}
                    </md-table-cell>
                    <md-table-cell md-label="Inrush Limit" md-sort-by="inrush_limit">{{ item.inrushLimit }}
                    </md-table-cell>
                </md-table-row>
            </md-table>

        </widget>
        <md-progress-bar md-mode="indeterminate" v-if="loading"/>
        <redirection :redirection-url="redirectionUrl" :dialog-active="redirectDialogActive"/>
    </div>
</template>

<script>
import Widget from '../Shared/Widget'
import Redirection from '../Shared/Redirection'
import { MeterModelService } from '../../services/MeterModelService'
import { EventBus } from '../../eventbus'
import { SystemService } from '../../services/SystemService'

export default {
    name: 'MeterModelList',
    components: { Widget, Redirection },
    data () {
        return {
            systemService: new SystemService(),
            meterModelService: new MeterModelService(),
            subscriber: 'meter-model-list',
            searchTerm: '',
            loading: false,
            isSynced: false,
            title: 'Meter Models',
            redirectionUrl: '/spark-meters/sm-overview',
            redirectDialogActive: false,
            buttonText: 'Get Updates From Spark Meter',
            label: 'Meter Model Records Not Up to Date.'
        }
    },
    mounted () {
        this.getSystem()
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
                this.redirectDialogActive = true
            }
        },

        async checkSync () {
            try {
                this.loading = true
                this.isSynced = await this.meterModelService.checkMeterModels()
                this.loading = false
                if (!this.isSynced) {
                    let swalOptions = {
                        title: 'Updates',
                        showCancelButton: true,
                        text: 'Meter Model Records Not Up to Date.',
                        confirmButtonText: 'Update',
                        cancelButtonText: 'Cancel',
                    }
                    this.$swal(
                        swalOptions
                    ).then((result) => {
                        if (result.value) {
                            this.syncMeterModels()
                        }
                    })
                }
            } catch (e) {
                this.loading = false
                this.alertNotify('error', e.message)
            }
        },
        async syncMeterModels () {
            if (!this.loading) {
                try {
                    this.loading = true
                    this.isSynced = false
                    await this.meterModelService.syncMeterModels()
                    EventBus.$emit('widgetContentLoaded', this.subscriber, 1)
                    this.isSynced = true
                    this.loading = false
                } catch (e) {
                    this.loading = false
                    this.alertNotify('error', e.message)
                }
            }

        },
        reloadList (subscriber, data) {
            if (subscriber !== this.subscriber) return
            this.meterModelService.updateList(data)
            EventBus.$emit('widgetContentLoaded', this.subscriber, this.meterModelService.list.length)
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
