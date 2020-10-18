<template>
    <div>

        <div  class="Container-smp-title" >
            <label class="card-title" > System Information </label><br>

        </div>

        <div class="Container-smp-notify">
            <div class="md-layout md-gutter">
                <div class="md-layout-item  md-size-40">
                    <label class="CustomLabel" >Credential</label>
                </div>
                <div class="md-layout-item  md-size-50">
                    <md-icon v-if="isValid" style="color:#1a921a">check_circle_outline</md-icon>
                    <label class="CustomLabel" v-if="isValid"> Authorized</label>
                    <md-icon v-if="!isValid" style="color:#d01111">remove</md-icon>
                    <label class="CustomLabel" v-if="!isValid"> Unauthorized</label>
                </div>
                <div class="md-layout-item  md-size-10"></div>
                <div class="md-layout-item  md-size-40">
                    <label class="CustomLabel" >ID :</label>
                </div>
                <div class="md-layout-item  md-size-50">
                    <label class="CustomLabel" > {{systemService.grid.id}}</label>
                </div>
                <div class="md-layout-item  md-size-10"></div>
                <div class="md-layout-item  md-size-40">
                    <label class="CustomLabel" >Last Sync Date :</label>
                </div>
                <div class="md-layout-item  md-size-50">
                    <label class="CustomLabel"  >{{systemService.grid.lastSyncDate}}</label>
                </div>
                <div class="md-layout-item  md-size-10"></div>
                <div class="md-layout-item  md-size-40">
                    <label class="CustomLabel" >Name :</label>
                </div>
                <div class="md-layout-item  md-size-50">
                    <label class="CustomLabel"  >{{systemService.grid.name}}</label>
                </div>
                <div class="md-layout-item  md-size-10"></div>
                <div class="md-layout-item  md-size-40">
                    <label class="CustomLabel" >Serial :</label>
                </div>
                <div class="md-layout-item  md-size-50">
                    <label class="CustomLabel" >{{systemService.grid.serial}}</label>
                </div>
                <div class="md-layout-item  md-size-10"></div>
            </div>
        </div>
    </div>
</template>

<script>
import { SystemService } from '../../services/SystemService'
import { EventBus } from '../../eventbus'

export default {
    name: 'SystemInformation',
    data () {
        return {
            systemService:new SystemService(),
            isValid:false
        }
    },
    mounted () {
        this.getSystem()
        EventBus.$on('credentialUpdated',()=>{
            this.getSystem()
        })
    },
    methods:{
        async getSystem () {
            try {
                await this.systemService.getSystemInfo()
                this.isValid=true
            } catch (e) {
                this.isValid=false
            }
        }
    }
}
</script>

<style scoped>

    .Container-smp-title{
        display: flex;
        flex-direction: column;
        word-wrap: break-word;
        background-clip: border-box;
        margin: 0 auto;
        position: relative;
        box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
        border-radius: 3px;
        height: 61px;

    }

    .Container-smp-notify{
        display: flex;
        flex-direction: column;
        word-wrap: break-word;
        background-clip: border-box;
        margin: 0 auto;
        position: relative;
        box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
        border-radius: 3px;
        height: 261px;
        padding: 20px;
    }
    .Container-smp-notify label{
        font-size: 14px;
    }
    .Container-smp-notify small{
        font-size: 12px;
    }
    .Container-smp-title label{
        margin-top: 2%;
        font-size: 14px;
        margin-left: 5px;
    }
    .CustomLabel{
        font-size: 12px
    }

    @media screen and (max-width: 960px) {
        .Container-smp-notify{
            display: flex;
            flex-direction: column;
            word-wrap: break-word;
            background-clip: border-box;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
            border-radius: 3px;

            padding: 20px;
            height: 280px ;
        }
        .CustomLabel{
            font-size: 11px
        }

    }
    @media screen and (max-width: 600px) {
        .CustomLabel{
            font-size: 11px
        }
        .Container-smp-notify{
            display: flex;
            flex-direction: column;
            word-wrap: break-word;
            background-clip: border-box;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 3px 1px -2px rgba(0,0,0,.2), 0 2px 2px 0 rgba(0,0,0,.14), 0 1px 5px 0 rgba(0,0,0,.12);
            border-radius: 3px;

            padding: 20px;
            height: 330px   ;
        }
    }
</style>
