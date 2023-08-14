export class ErrorResponse {
  public errorCode?: string;

  public constructor(data: any) {
    if (data.error_code) {
      this.errorCode = data.error_code;
    }
  }
}
