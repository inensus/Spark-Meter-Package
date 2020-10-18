<template>
    <md-card class="mat-card mat-focus-indicator">
        <div  :class="setColor">
            <div class="header"> {{header}}</div>
            <div class="text">Registered</div>
            <div class="count">

                <md-icon  class="custom-icon" style="display: flow-root; font-size: 4rem !important">
                    {{icon}}
                </md-icon>
                <div class="temp align-self-center">
                    {{count}}</div>

            </div>
        </div>
    </md-card>

</template>

<script>
export default {
    name: 'OverviewBox',
    props: {
        header:{
            type:String,
            default:''
        },
        count:{
            type:Number,
            default: 0
        },
        icon:{
            type:String,
            default:''
        },
        color:{
            type:String,
            default:''
        }
    },
    methods:{

    },
    computed:{
        setColor: function () {
            if (this.color === 'green') {
                return 'card-wheather grad-green'
            } else if (this.color === 'red') {
                return 'card-wheather grad-red'
            } else if (this.color === 'blue') {
                return 'card-wheather grad-blue'
            }else{
                return 'card-wheather grad-blue'
            }
        }
    }

}
</script>
<style lang="scss" scoped>
    .md-card {
        transition: box-shadow 280ms cubic-bezier(0.4, 0, 0.2, 1);
        display: block;
        position: relative;
        padding: 0.5rem;
    }
    .background  {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: #fff;
        border-radius: 2px
    }

    .card-wheather {
        height: 11rem;
        color: #fff
    }

    .header {
        font-size: 26px
    }

    .text {
        font-size: 0.8rem;
        line-height: 3rem;
    }

    .count {
        height: 70px
    }
    .align-self-center {
        align-self: center!important;
        float: right;
    }
    .grad-blue {
        background: linear-gradient(58deg, #3e52b3, #1b181f);
        color: #fff
    }

    .grad-green {
        background: linear-gradient(58deg, #6aa71af7, #0e0e0e);
        color: #fff
    }

    .grad-red  {
        background: linear-gradient(58deg, #f44336, #332944);
        color: #fff
    }
    .custom-icon{
        font-size: larger;
        color: white;
    }
    .card-wheather  {
        padding: 2rem
    }

    .temp  {
        font-size: 50px;
        display: inline-block
    }

</style>
