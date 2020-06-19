import { Autowired, Service, BusinessException, Page } from 'jbean'

import UserEntity from './entity/user'
import UserRepository from './repository/UserRepository'

@Service
export default class UserService {

  @Autowired
  private userRepository: UserRepository

  constructor () {
  }

  public beforeCall () {
  }

  public async hello (user: UserEntity) {
    let u: UserEntity = await this.userRepository.find(user)
    return u
  }

}

