import React from 'react';
import { Avatar, Badge, Text } from '@mantine/core';
import googleClassroom from '@/lib/config/googleClassroom';

async function getSubmissionCount(courseId: string, courseWorkId: string) {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.courseWork.studentSubmissions.list({
    courseId,
    courseWorkId,
    states: ['TURNED_IN'],
  });

  return data.studentSubmissions?.length;
}

async function getStudentsCount(courseId: string) {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.students.list({
    courseId,
  });
  return data.students?.length;
}

type Props = {
  courseId: string;
  courseWorkId: string;
};

async function SubmissionCount({ courseId, courseWorkId }: Props) {
  const [submissions, students] = await Promise.all([
    getSubmissionCount(courseId, courseWorkId),
    getStudentsCount(courseId),
  ]);

  await new Promise((resolve) => setTimeout(resolve, 1000));

  return (
    <Avatar size='sm' color='teal'>
      {submissions}/{students}
    </Avatar>
  );
}

export default SubmissionCount;
