# IUCN-WHO Theme

- [Setup](#setup)
- [Grunt usage](#gruntusage)

## Setup {#setup}
- [Less]
- [npm]
- [Grunt]



## Grunt usage {#gruntusage}
- in Terminal, navigate to theme folder and `npm install`
- First time setting up & for production build:
    - run `grunt build`
- During development:
    - run `grunt start` to build and start the watcher

**WARNING:** Do not modify the files inside of
`./iucn_who/bootstrap` directly. Doing so may cause issues when upgrading the
[Bootstrap Framework] in the future.

[Bootstrap Framework]: http://getbootstrap.com
[Less]: http://lesscss.org
[Grunt]: https://gruntjs.com
[npm]: https://npmjs.com
