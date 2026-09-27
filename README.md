# Zittme Lodging

[Zittme](https://github.com/zittme/zittme) 엔진용 숙박 예약 모듈입니다. 호텔·모텔·펜션·게스트하우스의 객실 판매와 예약을 전용 운영 콘솔에서 관리합니다.

## 요구 사항

- Zittme 1.0.0 이상
- 선결제를 쓰려면 [zittme-pay](https://github.com/zittme/zittme_pay) 모듈이 필요합니다. 없어도 현장결제로 운영할 수 있습니다.

## 설치

Zittme 설치 경로의 `modules/lodging` 에 이 저장소의 내용을 놓습니다.

```bash
cd 설치경로/modules
git clone https://github.com/zittme/lodging.git lodging
```

압축 파일로 받았다면 `modules/lodging/` 에 풀면 됩니다. 관리자 화면에 접속하면 테이블 생성이 자동으로 진행됩니다. 결제 연동 이벤트를 등록하려면 설치 뒤 관리자 화면에서 모듈 업데이트를 한 번 실행합니다.

## 주요 기능

- 업소 · 객실 타입 · 호실 관리, 날짜별 재고와 판매 중지
- 요금 달력: 주중/금/토 기본가와 특정일 지정가
- 숙박과 대실, 대실 마감이 입실 시각보다 늦으면 같은 객실 재고를 나눠 씀
- 최소·최대 숙박일, 기준 인원 초과 요금, 연박 할인
- 선결제(결제 대기 10분, 시간이 지나면 자동 해제, 늦게 들어온 결제는 자동 환불)와 현장결제
- 취소 규정 스냅샷과 규정에 따른 환불
- 쿠폰 · 쿠폰함 · 포인트, 이용 완료 예약자만 쓰는 후기
- 회원·비회원 예약, 비회원 조회
- 스킨 방식의 프런트 화면 (기본 스킨 포함)

## 라이선스

[GPL v2](LICENSE)

## 문의

- 홈페이지: https://zitt.me
- 매뉴얼: https://zitt.me/manual
