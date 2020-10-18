const resource = '/api/spark-meters/sm-system'
import Client from './Client/AxiosClient'

export default {
    get () {
        return Client.get(`${resource}`)
    },
}
