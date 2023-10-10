import { formatDate } from '@/lib/utils/format';
import { Title, Text, Anchor, Breadcrumbs } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import Link from 'next/link';
import { Suspense } from 'react';
import CourseLink from './CourseLink';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function Heading({ courseWork }: Props) {
  const items = [
    <Anchor component={Link} href={'/courses'} key={1}>
      Courses
    </Anchor>,
    <Suspense fallback={<div>Loading...</div>} key={2}>
      <CourseLink courseId={courseWork.courseId} />
    </Suspense>,
  ];

  return (
    <>
      <Breadcrumbs>{items}</Breadcrumbs>
      <Title mt={'lg'}>{courseWork.title}</Title>
      <div>
        <Text tt='capitalize'>
          {courseWork.workType?.toLowerCase()}
          <Text component={'span'} c='dimmed' size='sm'>
            &nbsp; ({courseWork.maxPoints} Points)
          </Text>
        </Text>
        <Text size='sm' c='dimmed'>
          Due: {formatDate(courseWork.dueDate, courseWork.dueTime)}
        </Text>
        <Text tt='capitalize' size='sm' c='dimmed'>
          {courseWork.state?.toLocaleLowerCase()}
        </Text>
      </div>
    </>
  );
}
