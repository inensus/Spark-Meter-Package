<template>
    <div>
        <div class="overview-line">
            <div class="md-layout md-gutter">

                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100 md-size-33">

                    <box


                        :center-text="true"
                        :color="[ '#ef5350','#e53935']"
                        :sub-text="meterModelService.count.toString()"
                        :header-text-color="'#dddddd'"
                        header-text="Meter Models"
                        :sub-text-color="'#e3e3e3'"
                        box-icon="plug"
                        :box-icon-color="'#604058'"
                    />
                </div>
                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100 md-size-33">

                    <box

                        :center-text="true"
                        :color="[ '#6eaa44','#578839']"
                        :sub-text="tariffService.count.toString()"
                        :header-text-color="'#dddddd'"
                        header-text="Tariffs "
                        :sub-text-color="'#e3e3e3'"
                        :box-icon="'money-bill'"
                        :box-icon-color="'#5c5837'"
                    />
                </div>
                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100 md-size-33">

                    <box

                        :center-text="true"
                        :color="[ '#ffa726','#fb8c00']"
                        :sub-text="customerService.count.toString()"
                        :header-text-color="'#dddddd'"
                        header-text="Customers"
                        :sub-text-color="'#e3e3e3'"
                        :box-icon="'user'"
                        :box-icon-color="'#385a76'"

                    />
                </div>

            </div>

        </div>
        <div class="overview-line">
            <div class="md-layout md-gutter">
                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100  md-size-50">
                    <SystemInformation/>
                </div>

                <div class="md-layout-item md-small-size-100  md-xsmall-size-100 md-medium-size-100  md-size-50">
                    <credential style="height: 100%!important;"/>
                </div>
            </div>

        </div>


    </div>
</template>

<script>

import Box from './Box'
import Credential from './Credential'
import { CustomerService } from '../../services/CustomerService'
import { MeterModelService } from '../../services/MeterModelService'
import { TariffService } from '../../services/TariffService'
import SystemInformation from './SystemInformation'

export default {
    name: 'Overview',
    components: { Credential, SystemInformation, Box },
    data () {
        return {
            customerService: new CustomerService(),
            meterModelService: new MeterModelService(),
            tariffService: new TariffService(),
            meterModelsCount: 0,
            tariffsCount: 0
        }
    },
    mounted () {
        this.getCustomersCount()
        this.getMetermodelsCount()
        this.getTariffsCount()
    },
    methods: {
        async getCustomersCount () {
            await this.customerService.getCustomersCount()
        },
        async getMetermodelsCount () {
            await this.meterModelService.getMeterModelsCount()
        },
        async getTariffsCount () {
            await this.tariffService.getTariffsCount()
        }

    }
}
</script>

<style scoped>
    .overview-line {
        margin-top: 1rem;
    }
</style>
