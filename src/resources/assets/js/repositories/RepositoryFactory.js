import CredentialRepository from './CredentialRepository'
import CustomerRepository from './CustomerRepository'
import MeterModelRepository from './MeterModelRepository'
import TariffRepository from './TariffRepository'
import PaginatorRepository from './PaginatorRepository'
import SystemRepository from './SystemRepository'

const repositories = {
    'credential':CredentialRepository,
    'customer':CustomerRepository,
    'meterModel':MeterModelRepository,
    'tariff':TariffRepository,
    'paginate':PaginatorRepository,
    'system':SystemRepository
}
export default {
    get: name => repositories[name]
}
