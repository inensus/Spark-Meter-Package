import Repository from '../repositories/RepositoryFactory'
import { ErrorHandler } from '../Helpers/ErrorHander'

export class MeterModelService {
    constructor () {
        this.repository = Repository.get('meterModel')
        this.list=[]
        this.isSync=false
        this.count=0
        this.pagingUrl='/api/spark-meters/sm-meter-model'
        this.routeName='/spark-meters/sm-meter-model'
    }
    fromJson (meterModelsData) {
        this.list=[]
        for (let m in meterModelsData) {
            let meterModel={
                id :meterModelsData[m].id,
                modelName :meterModelsData[m].model_name,
                continuousLimit :meterModelsData[m].continuous_limit,
                inrushLimit :meterModelsData[m].inrush_limit
            }
            this.list.push(meterModel)
        }
    }
    updateList (data) {
        this.list = []
        return this.fromJson(data)
    }
    async getMeterModels () {
        try {
            let response = await this.repository.list()
            if (response.status === 200) {
                return this.fromJson(response.data.data)
            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }
    async syncMeterModels () {
        try {
            let response = await this.repository.sync()
            if (response.status === 200) {
                return this.fromJson(response.data.data)
            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }
    async checkMeterModels () {
        try {
            let response = await this.repository.syncCheck()
            if (response.status === 200) {
                return response.data.data.result

            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }
    async getMeterModelsCount () {
        try {
            let response = await this.repository.count()
            if (response.status === 200) {
                this.count = response.data
                return this.count
            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }
}
