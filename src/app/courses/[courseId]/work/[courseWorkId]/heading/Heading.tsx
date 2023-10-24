import { formatDate } from '@/lib/utils/format';
import { Title, Text } from '@mantine/core';
import { classroom_v1 } from 'googleapis';
import CourseWorkBreadcrumbs from './CourseWorkBreadcrumbs';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function Heading({ courseWork }: Props) {
  return (
    <>
      <CourseWorkBreadcrumbs courseWork={courseWork} />
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
