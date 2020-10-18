import Repository from '../repositories/RepositoryFactory'
import { ErrorHandler } from '../Helpers/ErrorHander'


export class CredentialService {
    constructor () {
        this.repository = Repository.get('credential')
        this.credential = {
            id: null,
            apiUrl: null,
            authenticationToken: null
        }
    }

    fromJson (credentialData) {
        this.credential = {
            id: credentialData.id,
            apiUrl: credentialData.api_url,
            authenticationToken: credentialData.authentication_token,
        }
        return this.credential
    }

    async getCredential () {
        try {
            let response = await this.repository.get()
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

    async updateCredential(){
        try {
            let credentialPM = {
                id : this.credential.id,
                api_url: this.credential.apiUrl,
                authentication_token: this.credential.authenticationToken
            }
            let response = await this.repository.put(credentialPM)
            if (response.status === 200 || response.status === 201 ) {

                return this.fromJson(response.data.data)
            } else {
                return new ErrorHandler(response.error, 'http', response.status)
            }
        } catch (e) {
            let errorMessage = e.response.data.data.message
            return new ErrorHandler(errorMessage, 'http')
        }
    }

    async checkCredential(){
        try {
            let response = await this.repository.check()
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
}
