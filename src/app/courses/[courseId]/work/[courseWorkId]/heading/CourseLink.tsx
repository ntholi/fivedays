import googleClassroom from '@/lib/config/googleClassroom';
import { Anchor } from '@mantine/core';
import Link from 'next/link';
import React from 'react';

type Props = {
  courseId?: string | null;
};

const getCourseName = async (courseId: string | null | undefined) => {
  if (!courseId) {
    return '';
  }
  const classroom = await googleClassroom();
  const { data: course } = await classroom.courses.get({
    id: courseId,
  });
  return course.name;
};

export default async function CourseLink({ courseId }: Props) {
  const courseName = await getCourseName(courseId);
  return (
    <Anchor component={Link} href={`/courses/${courseId}`} key={1}>
      {courseName}
    </Anchor>
  );
}
