# XE Migration Tool
XE3으로의 데이터를 이전하기 위한 사이트 마이그레이션 도구입니다.

## XE1 마이그레이션
XE 1.x 버전에서 XE3으로의 데이터 이전을 위해 다음과 같은 순서대로 진행합니다.

### 1 설정파일 생성
아래의 코드에서 `path` 경로를 변경하여 `secure-key-`로 시작하는 UUID를 포함하는 파일을 생성하여 아래 코드 내용으로 저장합니다.
이 파일은 설정 파일인 동시에 접근을 제한하기 위한 용도로 사용됩니다.

```
[common]
source=xpressengine1
# XE 1.x가 설치된 경로
path=./path/to/xe
# 데이터 업데이트 시 변경. 기본 값 1
revision=1

[user]
attach=true
limit=100

[document]
attach=false
limit=100
```

UUID는 `uuidgen` 명령 또는 https://www.uuidgenerator.net 등에서 생성할 수 있으며, 아래와 같이 파일명을 가져야 합니다.
아래 파일명은 예시이며, 권한 없는자의 접근이 가능할 수 있으므로 이 파일명을 사용하지 마시기 바랍니다.

생성한 UUID 값이 `40F2C56A-9B7A-425E-AE25-8959E38E73BE`일 경우
```
secure-key-40F2C56A-9B7A-425E-AE25-8959E38E73BE
```

### 2 secure key 인증
위 설정이 정상적이라면 'secure key를 입력하세요' 메시지가 출력되며, 위에서 생성한 UUID 값을 입력하면 됩니다.

### 3 데이터 선택
'data esport tool' 제목이 표시되고 추출할 대상 데이터를 선택하는 페이지입니다.

회원 정보 및 게시판의 게시물을 선택하여 데이터 이전을 할 수 있습니다.

![](https://raw.githubusercontent.com/xpressengine/xpressengine-migration/master/assets/step2.png)

#### 회원
그룹, 확장필드, 이메일, 이메일 인증 여부, ID, 닉네임, 패스워드, 프로필 이미지 등의 개인정보를 추출합니다.

#### 게시물
게시판의 카테고리와 확장필드 설정과, 첨부파일을 포함하는 게시물 데이터를 추출합니다.

### 3 데이터 가져오기
데이터를 가져오려는 XE3 사이트에 [Importer 플러그인](https://github.com/xpressengine/xpressengine-migration)을 설치한 후 활성화 후 다음과 같은 단계를 거칩니다.

`CURL config` 링크의 주소를 복사하여 아래와 같이 command line에서 사용할 수 있습니다.


```bash
php artisan importer:import "curl config 주소" --batch
```
