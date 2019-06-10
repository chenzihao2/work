import { Autowired, Repository } from 'jbean'
import UserEntity from '../entity/user'

@Repository
export default class UserRepository {


  constructor () {
  }

  public async createUser (user: UserEntity) {
  }

  public async deleteUser (condition: any) {
  }

  public async updateUser (user: UserEntity, condition: any) {
  }

  public async getUsers (condition: any) {
  }

  public async getUser (condition: object) {
  }

  public async helloMongo () {
  }

  public async helloRedis () {
    return new Promise((resolve, reject) => {
    })
  }

}

