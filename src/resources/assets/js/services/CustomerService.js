import Repository from '../repositories/RepositoryFactory'
import { ErrorHandler } from '../Helpers/ErrorHander'


export class CustomerService {
    constructor () {
        this.repository = Repository.get('customer')
        this.list=[]
        this.isSync=false
        this.count=0
        this.pagingUrl='/api/spark-meters/sm-customer'
        this.routeName='/spark-meters/sm-customer'
    }
    fromJson (customersData) {
        this.list=[]
        for (let c in customersData) {
            let customer={
                id :customersData[c].id,
                name :customersData[c].mpm_person.name,
                sparkId:customersData[c].customer_id
            }
            this.list.push(customer)
        }
    }
    updateList (data) {
        this.list = []
        return this.fromJson(data)
    }
    async getCustomers () {
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
    async syncCustomers () {
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
    async checkCustomers () {
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
    async getCustomersCount(){
        try {
            let response = await this.repository.count()
            if (response.status === 200) {
                this.count=  response.data
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
