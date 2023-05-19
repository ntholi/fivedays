interface StudentSubmission {
  id: string;
  userId: string;
  studentName: string;
  courseWorkId: string;
  alternateLink: string;
  draftGrade: number;
  late: boolean;
  updateTime: string;
  attachments?: SubmissionAttachment[];
}

interface SubmissionAttachment {
  type: 'link' | 'driveFile' | 'youtubeVideo';
  id?: string;
  title: string;
  url: string;
}
