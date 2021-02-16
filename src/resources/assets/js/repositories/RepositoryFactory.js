import CredentialRepository from './CredentialRepository'
import CustomerRepository from './CustomerRepository'
import MeterModelRepository from './MeterModelRepository'
import TariffRepository from './TariffRepository'
import PaginatorRepository from './PaginatorRepository'
import SiteRepository from './SiteRepository'
import SettingRepository from './SettingRepository'
import SmsSettingRepository from './SmsSettingRepository'
import SyncSettingRepository from './SyncSettingRepository'
const repositories = {
    'credential':CredentialRepository,
    'customer':CustomerRepository,
    'meterModel':MeterModelRepository,
    'tariff':TariffRepository,
    'paginate':PaginatorRepository,
    'site':SiteRepository,
    'setting': SettingRepository,
    'smsSetting': SmsSettingRepository,
    'syncSetting': SyncSettingRepository
}
export default {
    get: name => repositories[name]
}
