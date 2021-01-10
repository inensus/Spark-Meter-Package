import CredentialRepository from './CredentialRepository'
import CustomerRepository from './CustomerRepository'
import MeterModelRepository from './MeterModelRepository'
import TariffRepository from './TariffRepository'
import PaginatorRepository from './PaginatorRepository'
import SiteRepository from './SiteRepository'

const repositories = {
    'credential':CredentialRepository,
    'customer':CustomerRepository,
    'meterModel':MeterModelRepository,
    'tariff':TariffRepository,
    'paginate':PaginatorRepository,
    'site':SiteRepository
}
export default {
    get: name => repositories[name]
}
