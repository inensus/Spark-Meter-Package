import Repository from '../repositories/RepositoryFactory'
import { ErrorHandler } from '../Helpers/ErrorHander'
export class SystemService {
    constructor () {
        this.repository = Repository.get('system')
        this.grid={
            id:null,
            lastSyncDate:null,
            name:null,
            serial:null
        }
    }

    fromJson(gridData){
        this.grid={
            id:gridData.id,
            lastSyncDate: gridData.last_sync_date,
            name:gridData.name,
            serial: gridData.serial
        }

    }

    async getSystemInfo(){
        try {
            let response = await this.repository.get()
            if (response.status === 200) {
                return this.fromJson(response.data.data[0])
            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }
}
