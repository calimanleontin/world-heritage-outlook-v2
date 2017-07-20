# IUCN-WHO Theme

- [Setup](#setup)
- [Grunt usage](#gruntusage)

## Setup {#setup}
- [Less]
- [npm]
- [Grunt]



## Grunt usage {#gruntusage}
- in Terminal, navigate to theme folder and `npm install`
- run `grunt` to only compile LESS without PostCSS and watch
- run `grunt prod` to build for production (PostCSS)

**WARNING:** Do not modify the files inside of
`./iucn_who/bootstrap` directly. Doing so may cause issues when upgrading the
[Bootstrap Framework] in the future.

[Bootstrap Framework]: http://getbootstrap.com
[Less]: http://lesscss.org
[Grunt]: https://gruntjs.com
[npm]: https://npmjs.com
