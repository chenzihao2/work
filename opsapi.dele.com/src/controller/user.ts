import { Autowired, BusinessException } from 'jbean'
import { Controller, Get, Post, Request, Response, Transactional, Validation, ValidationMode, Cache } from 'jweb'

import Auth from '../annos/Auth'
import ResponseBody from '../annos/response_body'

import UserService from '../lib/account/UserService'
import UserEntity from '../lib/account/entity/user'

import BaseController from './base'

@Controller('/user')
@Transactional
@Auth
export default class User extends BaseController {

  @Autowired
  private userService: UserService

  constructor () {
    super()
  }

  @Get('/process/{uid}')
  @ResponseBody('json')
  @Validation(UserEntity)
  public async process (req: Request, res: Response, { uid }) {
    const user: UserEntity = req.entity
    // throw new BusinessException('inner err', -100, null)
    let u = await this.userService.hello(user)

    let data = {
      a: 1,
      b: [2, 3, 4],
      uid: uid,
      u: u
    }
    return data
  }

}

